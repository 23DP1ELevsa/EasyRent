<?php

namespace App\Http\Controllers;

use App\Models\Atsauksme;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\ParkapumaZinojums as Report;
use App\Models\Persona;
use App\Models\Transportlidzeklis;
use App\Support\AccountRestrictions;
use App\Support\AdminChanges;
use App\Support\EmailVerification;
use App\Support\ModerationNotice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminModerationController extends Controller
{
    private const DURATIONS = ['24h' => 1, '3d' => 3, '7d' => 7, '14d' => 14, '30d' => 30, '90d' => 90];

    public function restrict(Request $request, int $id, EmailVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'veids' => ['required', Rule::in(Restriction::TYPES)],
            'iemesla_kods' => ['nullable', Rule::in(Report::REASONS)],
            'iemesls' => ['required', 'string', 'max:2000'],
            'duration' => ['required', Rule::in([...array_keys(self::DURATIONS), 'custom', 'indefinite'])],
            'beigu_laiks' => ['required_if:duration,custom', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
        ]);
        $actor = $request->user();

        return AdminChanges::run($actor, function ($administrators) use ($data, $actor, $id, $verification) {
            $target = Persona::whereKey($id)->lockForUpdate()->firstOrFail();
            $start = now()->toImmutable();
            $end = match ($data['duration']) {
                'custom' => CarbonImmutable::parse($data['beigu_laiks'])->utc(),
                'indefinite' => null,
                default => $start->addDays(self::DURATIONS[$data['duration']]),
            };
            if ($end?->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['beigu_laiks' => __('admin.future_date')]);
            }

            $type = $data['veids'];
            if ($type !== Restriction::WARNING) {
                abort_if($target->restrictions()->active()->where('veids', $type)->exists(), 409, __('admin.already_restricted'));
            }
            if ($type === Restriction::BLOCK) {
                AdminChanges::protectLastAdministrator($administrators, $target);
                $target->increment('session_generation');
                $target->tokens()->delete();
                $target->purgeDatabaseSessions();
                $verification->forget($target);
                if ($target->epasts !== null) {
                    Password::deleteToken($target);
                }
            }

            $restriction = $target->restrictions()->create([
                'administrators_id' => $actor->getKey(),
                'veids' => $type,
                'iemesla_kods' => $data['iemesla_kods'] ?? null,
                'iemesls' => $data['iemesls'],
                'sakuma_laiks' => $start,
                'beigu_laiks' => $end,
            ]);
            AdminChanges::record($actor, 'restriction_issued', 'user', $id, $restriction->iemesls, null,
                ['ierobezojums_id' => $restriction->getKey(), 'veids' => $type, 'beigu_laiks' => $end?->toISOString(), 'iemesla_kods' => $restriction->iemesla_kods]);
            ModerationNotice::afterCommit($target, $type, $restriction->iemesls, $this->mailDate($end), ongoing: true,
                reasonCode: $restriction->iemesla_kods);

            return response()->json($restriction, 201);
        });
    }

    public function revoke(Request $request, int $id, int $restrictionId): JsonResponse
    {
        $reason = $this->reason($request);
        $actor = $request->user();

        return AdminChanges::run($actor, function () use ($actor, $id, $restrictionId, $reason) {
            $target = Persona::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            $restriction = $target->restrictions()->whereKey($restrictionId)->active()->lockForUpdate()->firstOrFail();
            $restriction->forceFill([
                'atcelts_at' => now(),
                'atcela_persona_id' => $actor->getKey(),
                'atcelsanas_iemesls' => $reason,
            ])->save();

            $details = ['ierobezojums_id' => $restrictionId, 'veids' => $restriction->veids];
            AdminChanges::record($actor, 'restriction_revoked', 'user', $id, $reason,
                $details + ['atcelts_at' => null], $details + ['atcelts_at' => $restriction->atcelts_at->toISOString()]);
            ModerationNotice::afterCommit($target, 'restriction_revoked', $reason, $this->mailDate(now()->toImmutable()));

            return response()->json($restriction);
        });
    }

    public function vehicle(Request $request, int $id): JsonResponse
    {
        return $this->visibility($request, Transportlidzeklis::class, 'vehicle', $id);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        return $this->visibility($request, Atsauksme::class, 'review', $id);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'statuss' => ['required', Rule::in(['izskatisana', 'atrisinats', 'noraidits'])],
            'iemesls' => ['required', 'string', 'max:2000'],
        ]);
        $actor = $request->user();

        return AdminChanges::run($actor, function () use ($data, $actor, $id) {
            $report = Report::whereKey($id)->lockForUpdate()->firstOrFail();
            $oldStatus = $report->statuss;
            abort_if(! in_array($oldStatus, Report::OPEN, true) || $oldStatus === $data['statuss'], 409, __('admin.report_decided'));

            $final = ! in_array($data['statuss'], Report::OPEN, true);
            $report->forceFill([
                'statuss' => $data['statuss'],
                'administrators_id' => $actor->getKey(),
                'lemuma_komentars' => $data['iemesls'],
                'izskatits_at' => $final ? now() : null,
            ])->save();

            AdminChanges::record($actor, 'report_updated', 'report', $id, $data['iemesls'],
                ['statuss' => $oldStatus], ['statuss' => $report->statuss]);
            if ($final) {
                ModerationNotice::afterCommit($report->reporter, 'report_'.$report->statuss, $data['iemesls']);
            }

            return response()->json($report);
        });
    }

    private function visibility(Request $request, string $model, string $kind, int $id): JsonResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean'], 'iemesls' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();

        return AdminChanges::run($actor, function () use ($data, $model, $kind, $id, $actor) {
            /** @var Model $target */
            $target = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $hidden = (bool) $data['hidden'];
            $before = $target->moderation_hidden_at?->toISOString();
            abort_if($hidden === ($before !== null), 409, __('admin.state_unchanged'));

            $target->forceFill([
                'moderation_hidden_at' => $hidden ? now() : null,
                'moderation_reason' => $hidden ? $data['iemesls'] : null,
            ])->save();

            AdminChanges::record($actor, $kind.($hidden ? '_hidden' : '_restored'), $kind, $id, $data['iemesls'],
                ['moderation_hidden_at' => $before], ['moderation_hidden_at' => $target->moderation_hidden_at?->toISOString()]);

            if ($hidden && $target instanceof Transportlidzeklis && ($owner = $target->sniedzejs?->persona)) {
                ModerationNotice::afterCommit($owner, 'vehicle_hidden', $data['iemesls'], ongoing: true);
            }

            return response()->json(['id' => $id, 'moderation_hidden_at' => $target->moderation_hidden_at, 'moderation_reason' => $target->moderation_reason]);
        });
    }

    private function reason(Request $request): string
    {
        return $request->validate(['iemesls' => ['required', 'string', 'max:2000']])['iemesls'];
    }

    private function mailDate(?CarbonImmutable $date): ?string
    {
        return $date?->setTimezone('Europe/Riga')->format('Y-m-d H:i T');
    }
}
