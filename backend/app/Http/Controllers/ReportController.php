<?php

namespace App\Http\Controllers;

use App\Models\Atsauksme;
use App\Models\ParkapumaZinojums as Report;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use App\Support\AccountRestrictions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'objekta_veids' => ['required', Rule::in(Report::TARGETS)],
            'objekta_id' => ['required', 'integer', 'min:1'],
            'iemesls' => ['required', Rule::in(Report::REASONS)],
            'apraksts' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($actor, $data) {
                $fresh = Persona::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
                AccountRestrictions::assertCurrent($actor, $fresh);
                AccountRestrictions::assertUnblocked($fresh);

                $id = (int) $data['objekta_id'];
                $accessible = match ($data['objekta_veids']) {
                    'vehicle' => Transportlidzeklis::listed()->whereKey($id)->lockForUpdate()->first(),
                    'review' => Atsauksme::visible()->whereHas('transportlidzeklis', fn ($query) => $query->listed())
                        ->whereKey($id)->lockForUpdate()->first(),
                    'user' => $this->canReportUser($fresh, $id),
                };
                abort_unless($accessible, 404, __('admin.report_target_missing'));
                $report = Report::create($data + ['persona_id' => $actor->getKey()]);

                return response()->json(['id' => $report->getKey(), 'statuss' => $report->statuss ?? 'jauns'], 201);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, __('admin.report_duplicate'));
        }
    }

    private function canReportUser(Persona $actor, int $id): bool
    {
        if ((int) $actor->getKey() === $id || ! Persona::whereKey($id)->where('loma', '!=', 'administrators')->exists()) {
            return false;
        }

        if (Transportlidzeklis::listed()->whereHas('sniedzejs', fn ($query) => $query->where('persona_id', $id))->exists()
            || Atsauksme::visible()->whereHas('klients', fn ($query) => $query->where('persona_id', $id))
                ->whereHas('transportlidzeklis', fn ($query) => $query->listed())->exists()) {
            return true;
        }

        return match ($actor->loma) {
            'klients' => Rezervacija::whereIn('klients_id', $actor->klients()->select('klients_id'))
                ->whereHas('transportlidzeklis.sniedzejs', fn ($query) => $query->where('persona_id', $id))->exists(),
            'pakalpojumu_sniedzejs' => Rezervacija::whereHas('transportlidzeklis.sniedzejs', fn ($query) => $query->where('persona_id', $actor->getKey()))
                ->whereHas('klients', fn ($query) => $query->where('persona_id', $id))->exists(),
            default => false,
        };
    }
}
