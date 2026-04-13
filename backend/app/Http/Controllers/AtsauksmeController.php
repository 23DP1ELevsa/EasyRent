<?php

namespace App\Http\Controllers;

use App\Models\Atsauksme;
use App\Models\Persona;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AtsauksmeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'transportlidzeklis_id' => ['nullable', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
        ]);

        $query = Atsauksme::with(['klients.persona'])
            ->orderByDesc('datums')
            ->orderByDesc('created_at');

        if (!empty($data['transportlidzeklis_id'])) {
            $query->where('transportlidzeklis_id', $data['transportlidzeklis_id']);
        }

        $reviews = $query->get();

        $baseStatsQuery = Atsauksme::query();
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
            ->get();

        return response()->json([
            'atsauksmes' => $reviews,
            'statistika' => [
                'kopejais_vertejums' => $overallAverage,
                'kopejais_atsauksmju_skaits' => $overallCount,
                'transportlidzekli' => $vehicleStats,
            ],
        ]);
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
            return response()->json(['message' => 'Atsauksmi drīkst pievienot tikai autorizēts klients.'], 403);
        }

        $review = Atsauksme::updateOrCreate(
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

        $statusCode = $review->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'message' => $review->wasRecentlyCreated ? 'Atsauksme pievienota.' : 'Atsauksme atjaunināta.',
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
            return response()->json(['message' => 'Atsauksmi drīkst rediģēt tikai autorizēts klients.'], 403);
        }

        $review = Atsauksme::find($id);
        if (!$review) {
            return response()->json(['message' => 'Atsauksme nav atrasta.'], 404);
        }

        if ((int) $review->klients_id !== (int) $klients->klients_id) {
            return response()->json(['message' => 'Nav atļauts rediģēt cita klienta atsauksmi.'], 403);
        }

        $review->update([
            'vertejums' => $data['vertejums'],
            'komentars' => $data['komentars'] ?? null,
            'datums' => Carbon::today(),
        ]);

        return response()->json([
            'message' => 'Atsauksme atjaunināta.',
            'atsauksme' => $review->refresh()->load(['klients.persona', 'transportlidzeklis']),
        ]);
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
