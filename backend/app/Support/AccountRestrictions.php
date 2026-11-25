<?php

namespace App\Support;

use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AccountRestrictions
{
    public static function block(Persona $persona): ?Restriction
    {
        return $persona->restrictions()->blocking()->latest('ierobezojums_id')->first();
    }

    public static function blockDetails(Restriction $restriction): array
    {
        return [
            'code' => 'account_blocked',
            'message' => __('admin.account_blocked'),
            'reason' => $restriction->iemesls,
            'reason_code' => $restriction->iemesla_kods,
            'ends_at' => $restriction->beigu_laiks?->toISOString(),
            'support_email' => config('app.support_email'),
        ];
    }

    public static function assertUnblocked(Persona $persona): void
    {
        if ($block = self::block($persona)) {
            abort(response()->json(self::blockDetails($block), 403)->header('Cache-Control', 'no-store'));
        }
    }

    public static function assertAllowed(Persona $persona, string $type): void
    {
        $restriction = $persona->restrictions()->active()->whereIn('veids', [$type, Restriction::BLOCK])
            ->orderByRaw('case when veids = ? then 0 else 1 end', [Restriction::BLOCK])
            ->latest('ierobezojums_id')->first();
        if (! $restriction) {
            return;
        }
        if ($restriction->veids === Restriction::BLOCK) {
            abort(response()->json(self::blockDetails($restriction), 403)->header('Cache-Control', 'no-store'));
        }

        abort(response()->json([
            'code' => 'action_restricted',
            'message' => __('admin.action_restricted', ['type' => __('admin.restrictions.'.$type)]),
            'reason' => $restriction->iemesls,
            'reason_code' => $restriction->iemesla_kods,
            'ends_at' => $restriction->beigu_laiks?->toISOString(),
            'support_email' => config('app.support_email'),
        ], 403)->header('Cache-Control', 'no-store'));
    }

    public static function assertCurrent(Persona $authenticated, Persona $locked): void
    {
        abort_unless((int) $authenticated->session_generation === (int) $locked->session_generation
            && self::tokenIsCurrent($authenticated), 401, __('app.authenticationRequired'));
    }

    private static function tokenIsCurrent(Persona $persona): bool
    {
        $token = $persona->currentAccessToken();

        return ! ($token instanceof PersonalAccessToken) || ! $token->exists
            || $persona->tokens()->whereKey($token->getKey())->exists();
    }

    public static function sessionIsCurrent(Request $request, Persona $persona): bool
    {
        if (! $request->hasSession() || $request->user('web')?->getKey() !== $persona->getKey()) {
            return true;
        }

        return (int) $request->session()->get('account_session_generation', 0) === (int) $persona->session_generation;
    }

    public static function authenticationError(Request $request, Persona $persona): ?JsonResponse
    {
        $block = self::block($persona);
        if ($block) {
            return response()->json(self::blockDetails($block), 403)->header('Cache-Control', 'no-store');
        }
        if (! self::sessionIsCurrent($request, $persona)) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => __('app.authenticationRequired')], 401)->header('Cache-Control', 'no-store');
        }

        if (! self::tokenIsCurrent($persona)) {
            return response()->json(['message' => __('app.authenticationRequired')], 401)->header('Cache-Control', 'no-store');
        }

        return null;
    }
}
