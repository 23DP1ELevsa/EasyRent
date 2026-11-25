<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Rezervacija;
use App\Support\EmailVerification;
use App\Support\AccountRestrictions;
use App\Support\AdminChanges;
use App\Support\GoogleOAuth;
use App\Support\ReservationInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountDeletionController extends Controller
{
    public function check(Request $request, GoogleOAuth $google): JsonResponse
    {
        $persona = $request->user();

        if ($persona->loma === 'administrators') {
            AdminChanges::run($persona, fn ($administrators) => AdminChanges::protectLastAdministrator($administrators, $persona));
        }

        return response()->json($this->eligibility($persona) + [
            'confirmation_method' => $persona->parole !== null ? 'password' : 'google',
            'google_available' => $persona->google_id !== null && $google->configured(),
            'google_confirmed' => $google->deletionConfirmed($request, $persona),
        ])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, GoogleOAuth $google, EmailVerification $verification): JsonResponse|Response
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'in:DZĒST'],
            'password' => ['nullable', 'string', 'max:1024'],
        ], [
            'confirmation.required' => __('app.deletionConfirm'),
            'confirmation.in' => __('app.deletionConfirm'),
            'confirmation.string' => __('app.deletionConfirm'),
            'password.string' => __('app.passwordInvalid'),
            'password.max' => __('app.passwordInvalid'),
        ]);

        $blocked = DB::transaction(function () use ($request, $data, $google, $verification) {
            $administrators = $request->user()->loma === 'administrators' ? AdminChanges::lockAdministrators() : collect();
            $persona = Persona::whereKey($request->user()->getKey())->lockForUpdate()->first();
            abort_unless($persona, 401, __('app.authenticationRequired'));

            AccountRestrictions::assertCurrent($request->user(), $persona);
            AccountRestrictions::assertUnblocked($persona);
            AdminChanges::protectLastAdministrator($administrators, $persona);
            $eligibility = $this->eligibility($persona);
            if (! $eligibility['can_delete']) {
                return $eligibility;
            }

            if ($persona->parole !== null) {
                if (! isset($data['password']) || ! Hash::check($data['password'], $persona->parole)) {
                    throw ValidationException::withMessages(['password' => __('app.deletionPassword')]);
                }
            } elseif (! $google->deletionConfirmed($request, $persona)) {
                throw ValidationException::withMessages(['google' => __('app.deletionGoogle')]);
            }

            $this->archiveInvoices($persona);
            if ($persona->epasts !== null) {
                Password::deleteToken($persona);
            }
            $verification->forget($persona);
            $this->anonymize($persona);
            $persona->tokens()->delete();
            $persona->purgeDatabaseSessions();
            if ($persona->delete() !== true) {
                throw new \RuntimeException('The account could not be deleted.');
            }

            return null;
        }, 3);

        if ($blocked !== null) {
            return response()->json($blocked + [
                'message' => $blocked['blocking_reservations_count'] > 0
                    ? __('app.deletionReservations')
                    : __('app.deletionPayments'),
            ], 409)->header('Cache-Control', 'no-store');
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    private function reservations(Persona $persona): Builder
    {
        return match ($persona->loma) {
            'klients' => Rezervacija::whereIn('klients_id', $persona->klients()->select('klients_id')),
            'pakalpojumu_sniedzejs' => Rezervacija::whereHas('transportlidzeklis', fn ($query) => $query
                ->whereIn('sniedzejs_id', $persona->pakalpojumuSniedzejs()->select('sniedzejs_id'))),
            default => Rezervacija::whereRaw('1 = 0'),
        };
    }

    private function eligibility(Persona $persona): array
    {
        $reservations = $this->reservations($persona);
        $count = (clone $reservations)->where('beigu_laiks', '>', now())
            ->whereNot(fn ($query) => $query->expiredUnpaid())->count();
        $pendingPayments = $reservations->whereHas('maksajumi', fn ($query) => $query->where('statuss', 'gaida'))->count();

        $result = ['can_delete' => $count === 0 && $pendingPayments === 0, 'blocking_reservations_count' => $count];
        if ($count > 0 || $pendingPayments > 0) {
            $result['reason'] = $count > 0 ? 'active_or_upcoming_reservations' : 'pending_payments';
        }
        if ($pendingPayments > 0) {
            $result['blocking_payments_count'] = $pendingPayments;
        }

        return $result;
    }

    private function archiveInvoices(Persona $persona): void
    {
        $issued = fn ($query) => $query->where('statuss', 'apstiprinats')->whereNotNull('rekins');
        $this->reservations($persona)->whereHas('maksajumi', $issued)
            ->with(['maksajumi' => $issued, 'klients.persona', 'transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona'])
            ->chunkById(100, function ($reservations) {
                foreach ($reservations as $reservation) {
                    foreach ($reservation->maksajumi as $payment) {
                        ReservationInvoice::archive($reservation, $payment);
                    }
                }
            });
    }

    private function anonymize(Persona $persona): void
    {
        if ($client = $persona->klients) {
            $client->update(['lietotajvards' => null]);
            $client->atsauksmes()->whereNotNull('komentars')->update(['komentars' => null]);
        }

        if ($provider = $persona->pakalpojumuSniedzejs) {
            $provider->transportlidzekli()->update([
                'archived_at' => now(),
                'statuss' => 'neaktivs',
                'adrese' => '',
                'registracijas_numurs' => null,
            ]);
            $provider->update(array_fill_keys([
                'registracijas_numurs', 'iela', 'majas_numurs', 'dzivokla_numurs',
                'pilseta', 'pasta_indekss', 'latitude', 'longitude',
            ], null));
        }

        $persona->forceFill(array_fill_keys([
            'vards', 'uzvards', 'epasts', 'parole', 'google_id', 'kontakttalrunis', 'bankas_konts', 'email_verified_at',
        ], null) + ['profile_completed' => false])->save();
    }
}
