<?php

namespace App\Http\Controllers;

use App\Models\Atsauksme;
use App\Models\Persona;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Support\AccountRestrictions;
use App\Models\Transportlidzeklis;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtsauksmeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'transportlidzeklis_id' => ['nullable', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
        ]);

        $baseStatsQuery = Atsauksme::visible()->whereHas('transportlidzeklis', fn ($query) => $query->listed());
        $query = (clone $baseStatsQuery)->with(['klients.persona'])
            ->orderByDesc('datums')
            ->orderByDesc('created_at');

        if (!empty($data['transportlidzeklis_id'])) {
            $query->where('transportlidzeklis_id', $data['transportlidzeklis_id']);
        }

        $reviews = $query->get();

        if (!empty($data['transportlidzeklis_id'])) {
            $baseStatsQuery->where('transportlidzeklis_id', $data['transportlidzeklis_id']);
        }

        $overallCount = (clone $baseStatsQuery)->count();
        $overallAverage = $overallCount > 0
            ? round((float) (clone $baseStatsQuery)->avg('vertejums'), 2)
            : null;

        $vehicleStats = (clone $baseStatsQuery)
            ->select('transportlidzeklis_id')
            ->selectRaw('ROUND(AVG(vertejums), 2) as videjais_vertejums')
            ->selectRaw('COUNT(*) as atsauksmju_skaits')
            ->groupBy('transportlidzeklis_id')
            ->withCasts(['videjais_vertejums' => 'float', 'atsauksmju_skaits' => 'integer'])
            ->get();

        return response()->json([
            'atsauksmes' => $reviews,
            'statistika' => [
                'kopejais_vertejums' => $overallAverage,
                'kopejais_atsauksmju_skaits' => $overallCount,
                'transportlidzekli' => $vehicleStats,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transportlidzeklis_id' => ['required', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
            'vertejums' => ['required', 'integer', 'between:1,5'],
            'komentars' => ['nullable', 'string', 'max:2000'],
        ]);

        $klients = $this->resolveAuthorizedClient($request);
        if (!$klients) {
            return response()->json(['message' => __('app.reviewSignInToAdd')], 403);
        }

        $vehicle = Transportlidzeklis::listed()->with('sniedzejs')->findOrFail($data['transportlidzeklis_id']);
        $review = DB::transaction(function () use ($request, $vehicle, $klients, $data) {
            $this->ensureCanWrite($request->user(), $vehicle);

            return Atsauksme::updateOrCreate(
                [
                    'klients_id' => $klients->klients_id,
                    'transportlidzeklis_id' => $data['transportlidzeklis_id'],
                ],
                [
                    'vertejums' => $data['vertejums'],
                    'komentars' => $data['komentars'] ?? null,
                    'datums' => Carbon::today(),
                ]
            );
        }, 3);

        $statusCode = $review->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'message' => $review->wasRecentlyCreated ? __('app.reviewAdded') : __('app.reviewUpdated'),
            'atsauksme' => $review->load(['klients.persona', 'transportlidzeklis']),
        ], $statusCode);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'vertejums' => ['required', 'integer', 'between:1,5'],
            'komentars' => ['nullable', 'string', 'max:2000'],
        ]);

        $klients = $this->resolveAuthorizedClient($request);
        if (!$klients) {
            return response()->json(['message' => __('app.reviewSignInToEdit')], 403);
        }

        $review = Atsauksme::find($id);
        if (!$review) {
            return response()->json(['message' => __('app.reviewNotFound')], 404);
        }

        if ((int) $review->klients_id !== (int) $klients->klients_id) {
            return response()->json(['message' => __('app.reviewEditForbidden')], 403);
        }

        $vehicle = Transportlidzeklis::listed()->with('sniedzejs')->findOrFail($review->transportlidzeklis_id);
        DB::transaction(function () use ($request, $vehicle, $review, $data) {
            $this->ensureCanWrite($request->user(), $vehicle);
            $review = Atsauksme::whereKey($review->getKey())->lockForUpdate()->firstOrFail();
            $review->update([
                'vertejums' => $data['vertejums'],
                'komentars' => $data['komentars'] ?? null,
                'datums' => Carbon::today(),
            ]);
        }, 3);

        return response()->json([
            'message' => __('app.reviewUpdated'),
            'atsauksme' => $review->refresh()->load(['klients.persona', 'transportlidzeklis']),
        ]);
    }

    private function ensureCanWrite(Persona $actor, Transportlidzeklis $vehicle): void
    {
        $ownerId = $vehicle->sniedzejs->persona_id;
        $accounts = Persona::lockActiveAccounts($actor->getKey(), $ownerId);
        $locked = Transportlidzeklis::whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
        AccountRestrictions::assertCurrent($actor, $accounts[$actor->getKey()]);
        AccountRestrictions::assertAllowed($accounts[$actor->getKey()], Restriction::REVIEW);
        abort_if($locked->archived_at || $locked->moderation_hidden_at || AccountRestrictions::block($accounts[$ownerId]),
            404, __('app.notFound'));
    }

    private function resolveAuthorizedClient(Request $request): ?\App\Models\Klients
    {
        $persona = $request->user()?->load('klients');

        if (!$persona || $persona->loma !== 'klients') {
            return null;
        }

        return $persona->klients;
    }
}
