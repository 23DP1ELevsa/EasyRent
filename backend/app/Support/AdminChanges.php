<?php

namespace App\Support;

use App\Models\AdministratoraDarbiba;
use App\Models\Persona;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminChanges
{
    public static function lockAdministrators(): Collection
    {
        return Persona::where('loma', 'administrators')->orderBy('persona_id')->lockForUpdate()->get();
    }

    public static function protectLastAdministrator(Collection $administrators, Persona $target): void
    {
        if ($target->loma !== 'administrators' || ! $target->hasVerifiedEmail() || AccountRestrictions::block($target)) {
            return;
        }

        abort_unless($administrators->contains(fn (Persona $other) => ! $other->is($target)
            && $other->hasVerifiedEmail() && filled($other->epasts) && (filled($other->parole) || filled($other->google_id))
            && ! AccountRestrictions::block($other)), 409, __('admin.last_administrator'));
    }

    public static function run(Persona $actor, Closure $operation): mixed
    {
        return DB::transaction(function () use ($actor, $operation) {
            $administrators = self::lockAdministrators();
            $fresh = $administrators->first(fn (Persona $admin) => $admin->is($actor));
            abort_unless($fresh?->hasVerifiedEmail(), 403, __('admin.forbidden'));
            AccountRestrictions::assertCurrent($actor, $fresh);
            AccountRestrictions::assertUnblocked($fresh);

            return $operation($administrators);
        }, 3);
    }

    private static function auditReason(string $reason): string
    {
        // Keep free text useful without retaining account numbers or credential-bearing links.
        $patterns = [
            '~https?://[^\s<>"\']+[?#][^\s<>"\']*~iu',
            '~\b[A-Z]{2}\d{2}(?:[ -]?[A-Z0-9]){11,30}\b~iu',
            '~\b(?:Bearer\s+\S+|\d+\|[a-z0-9]{30,}|re_[a-z0-9_-]{12,})~iu',
            '~\b(?:password|parole|token|api[_ -]?key|secret)\s*[=:]\s*\S+~iu',
        ];

        return preg_replace($patterns, '[…]', $reason) ?? '[…]';
    }

    public static function record(Persona $actor, string $action, string $target, int $id, ?string $reason, ?array $before, ?array $after): void
    {
        AdministratoraDarbiba::create([
            'administrators_id' => $actor->getKey(),
            'darbiba' => $action,
            'objekta_veids' => $target,
            'objekta_id' => $id,
            'iemesls' => $reason === null ? null : self::auditReason($reason),
            'iepriekseja_vertiba' => $before,
            'jauna_vertiba' => $after,
        ]);
    }
}
