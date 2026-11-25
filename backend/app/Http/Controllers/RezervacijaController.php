<?php

namespace App\Http\Controllers;

use App\Mail\ReservationInvoiceMail;
use App\Models\Maksajums;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Support\AccountRestrictions;
use App\Models\Klients;
use App\Models\PakalpojumuSniedzejs;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use App\Support\ReservationInvoice;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RezervacijaController extends Controller
{
    private function resolveClientTimezone(Request $request): \DateTimeZone
    {
        $timezone = trim((string) $request->header('X-Timezone', ''));

        if ($timezone !== '') {
            try {
                return new \DateTimeZone($timezone);
            } catch (\Throwable $e) {
            }
        }

        return new \DateTimeZone((string) config('app.timezone', 'UTC'));
    }

    private function parseClientDateTime(string $value, Request $request): Carbon
    {
        return Carbon::parse($value, $this->resolveClientTimezone($request))->utc();
    }

    public function index(Request $request)
    {
        Rezervacija::cleanupAvailability();

        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => __('app.authenticationRequired')], 401);
        }

        $party = fn ($query) => $query->withTrashed()->select('persona_id', 'vards', 'uzvards', 'deleted_at');
        $query = Rezervacija::with([
            'klients:klients_id,persona_id',
            'klients.persona' => $party,
            'transportlidzeklis.sniedzejs:sniedzejs_id,persona_id',
            'transportlidzeklis.sniedzejs.persona' => $party,
            'transportlidzeklis.veids',
        ])->withExists(['maksajumi as invoice_available' => fn ($query) => $query
            ->where('statuss', 'apstiprinats')->whereNotNull('rekins')])->orderByDesc('izveides_datums');

        if ($persona->loma === 'klients') {
            $klients = $this->resolveAuthorizedClient($request);
            if ($klients instanceof \Illuminate\Http\JsonResponse) {
                return $klients;
            }

            $query->where('klients_id', $klients->klients_id);
        } elseif ($persona->loma === 'pakalpojumu_sniedzejs') {
            $sniedzejs = $this->resolveAuthorizedProvider($request);
            if ($sniedzejs instanceof \Illuminate\Http\JsonResponse) {
                return $sniedzejs;
            }

            $query->whereHas('transportlidzeklis', function ($transportQuery) use ($sniedzejs) {
                $transportQuery->where('sniedzejs_id', $sniedzejs->sniedzejs_id);
            });
        } else {
            return response()->json(['message' => __('app.roleForbidden')], 403);
        }

        $reservations = $query->get()->each(function (Rezervacija $reservation) {
            foreach ([$reservation->klients, $reservation->transportlidzeklis?->sniedzejs] as $party) {
                if ($party?->persona?->trashed()) {
                    $party->setAttribute('persona_deleted', true);
                    $party->setRelation('persona', null);
                    $party->makeHidden('persona_id');
                }
            }
            $reservation->transportlidzeklis?->makeHidden('adrese');
        });

        if ($persona->loma === 'klients') {
            $paid = $reservations->where('apmaksas_statuss', 'apmaksata');
            if ($paid->isNotEmpty()) {
                $publicVehicles = Transportlidzeklis::listed()->with('sniedzejs')
                    ->whereKey($paid->pluck('transportlidzeklis_id')->unique()->all())->get()->keyBy('transportlidzeklis_id');

                foreach ($paid as $reservation) {
                    $location = $publicVehicles->get($reservation->transportlidzeklis_id)?->sniedzejs?->publicPickupLocation();
                    if ($location && $location['address'] !== '') {
                        $reservation->setAttribute('pickup_location', $location);
                    }
                }
            }
        }

        return response()->json($reservations)->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        Rezervacija::cleanupAvailability();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $data = $request->validate([
            'transportlidzeklis_id' => ['required', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
            'sakuma_laiks' => ['required', 'date'],
            'beigu_laiks' => ['required', 'date', 'after:sakuma_laiks'],
        ]);

        $start = $this->parseClientDateTime($data['sakuma_laiks'], $request);
        $end = $this->parseClientDateTime($data['beigu_laiks'], $request);

        return $this->withLockedVehicle($request->user(), $klients, $data['transportlidzeklis_id'], function (Transportlidzeklis $transport) use ($klients, $start, $end) {
            abort_if($transport->archived_at || $transport->moderation_hidden_at || $transport->statuss !== 'pieejams', 409, __('app.vehicleUnavailable'));
            abort_if($start->lessThanOrEqualTo(Carbon::now()), 422, __('app.bookingStartInFuture'));
            abort_if($end->lessThanOrEqualTo($start), 422, __('app.bookingEndAfterStart'));

            $conflict = $transport->rezervacijas()->where('sakuma_laiks', '<', $end)->where('beigu_laiks', '>', $start)->exists();
            abort_if($conflict, 409, __('app.vehicleTimeConflict'));

            $days = max(1, (int) ceil($start->diffInSeconds($end) / 86400));
            $reservation = $transport->rezervacijas()->create([
                'klients_id' => $klients->getKey(),
                'sakuma_laiks' => $start,
                'beigu_laiks' => $end,
                'rezervacijas_datums' => Carbon::today(),
                'izveides_datums' => Carbon::now(),
                'kopa_summa' => $days * $transport->dienas_nomas_cena,
                'apmaksas_statuss' => 'neapmaksata',
                'transporta_snapshot' => $transport->bookingSnapshot(),
            ]);

            $reservation->load([
                'transportlidzeklis.veids',
                'transportlidzeklis.sniedzejs:sniedzejs_id,persona_id',
                'transportlidzeklis.sniedzejs.persona',
            ]);
            $reservation->transportlidzeklis->makeHidden('adrese');

            return response()->json($reservation, 201);
        }, newRental: true);
    }

    public function pay(Request $request, $id)
    {
        Rezervacija::cleanupAvailability();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $reservation = Rezervacija::findOrFail($id);
        abort_unless((int) $reservation->klients_id === (int) $klients->getKey(), 403, __('app.bookingPayForbidden'));

        [$rezervacija, $maksajums] = $this->withLockedVehicle($request->user(), $klients, $reservation->transportlidzeklis_id, function (Transportlidzeklis $transport) use ($id) {
            $reservation = $transport->rezervacijas()->lockForUpdate()->findOrFail($id);
            abort_if($reservation->apmaksas_statuss === 'apmaksata', 409, __('app.bookingAlreadyPaid'));
            abort_if($transport->archived_at || $transport->moderation_hidden_at, 409, __('app.vehicleUnavailable'));
            abort_if($reservation->maksajumi()->exists(), 409, __('app.bookingPaymentExists'));
            abort_if(now()->greaterThanOrEqualTo($reservation->sakuma_laiks), 409, __('app.bookingPaymentExpired'));

            $payment = $reservation->maksajumi()->create([
                'summa' => $reservation->kopa_summa,
                'statuss' => 'apstiprinats',
                'tranzakcijas_numurs' => 'TX-'.strtoupper(Str::random(12)),
                'rekins' => sprintf('ER-%s-%d', now()->format('YmdHis'), $reservation->getKey()),
            ]);

            $reservation->update(['apmaksas_statuss' => 'apmaksata', 'maksajuma_datums' => Carbon::today()]);
            if ($transport->statuss === 'pieejams') {
                $transport->update(['statuss' => 'aiznemts']);
            }
            ReservationInvoice::archive($reservation, $payment);

            return [$reservation, $payment];
        }, newRental: true);
        $this->sendReservationInvoiceEmails($rezervacija, $maksajums);

        return response()->json([
            'rezervacija' => $rezervacija->refresh(),
            'maksajums' => $maksajums,
        ]);
    }

    public function invoice(Request $request, int $id)
    {
        $reservation = Rezervacija::with('transportlidzeklis')->findOrFail($id);
        $persona = $request->user();
        $participant = match ($persona->loma) {
            'klients' => (int) $persona->klients?->getKey() === (int) $reservation->klients_id,
            'pakalpojumu_sniedzejs' => (int) $persona->pakalpojumuSniedzejs?->getKey() === (int) $reservation->transportlidzeklis->sniedzejs_id,
            default => false,
        };
        abort_unless($participant, 404, __('app.invoiceNotFound'));

        $payment = $reservation->maksajumi()->where('statuss', 'apstiprinats')->whereNotNull('rekins')->firstOrFail();
        $data = ReservationInvoice::viewData($reservation, $payment, $persona->loma === 'klients' ? 'client' : 'provider');

        return response()->view('emails.reservation-invoice', $data, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="EasyRent-'.$id.'.html"',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        Rezervacija::cleanupAvailability();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $reservation = Rezervacija::findOrFail($id);
        abort_unless((int) $reservation->klients_id === (int) $klients->getKey(), 403, __('app.bookingCancelForbidden'));

        return $this->withLockedVehicle($request->user(), $klients, $reservation->transportlidzeklis_id, function (Transportlidzeklis $transport) use ($id) {
            $reservation = $transport->rezervacijas()->lockForUpdate()->findOrFail($id);
            abort_if($reservation->apmaksas_statuss === 'apmaksata' || $reservation->maksajumi()->exists(), 409, __('app.bookingHasPayment'));
            $reservation->delete();

            return response()->json(['message' => __('app.bookingCanceled')]);
        });
    }

    private function withLockedVehicle(Persona $actor, Klients $client, int $vehicleId, Closure $operation, bool $newRental = false): mixed
    {
        $vehicle = Transportlidzeklis::with('sniedzejs')->findOrFail($vehicleId);

        return DB::transaction(function () use ($actor, $client, $vehicle, $operation, $newRental) {
            $ownerId = $vehicle->sniedzejs->persona_id;
            $accounts = Persona::lockActiveAccounts($client->persona_id, $ownerId);
            $transport = Transportlidzeklis::whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();
            $account = $accounts[$client->persona_id];
            AccountRestrictions::assertCurrent($actor, $account);
            if ($newRental) {
                AccountRestrictions::assertAllowed($account, Restriction::BOOKING);
                abort_if(AccountRestrictions::block($accounts[$ownerId]), 409, __('app.vehicleUnavailable'));
            } else {
                AccountRestrictions::assertUnblocked($account);
            }

            return $operation($transport);
        }, 3);
    }

    private function resolveAuthorizedClient(Request $request): Klients|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => __('app.authenticationRequired')], 401);
        }

        if ($persona->loma !== 'klients') {
            return response()->json(['message' => __('app.clientOnly')], 403);
        }

        $klients = $persona->klients;
        if (!$klients) {
            return response()->json(['message' => __('app.clientMissing')], 403);
        }

        return $klients;
    }

    private function resolveAuthorizedProvider(Request $request): PakalpojumuSniedzejs|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => __('app.authenticationRequired')], 401);
        }

        if ($persona->loma !== 'pakalpojumu_sniedzejs') {
            return response()->json(['message' => __('app.providerOnly')], 403);
        }

        $sniedzejs = $persona->pakalpojumuSniedzejs ?? $persona->pakalpojumu_sniedzejs;
        if (!$sniedzejs) {
            return response()->json(['message' => __('app.providerMissing')], 403);
        }

        return $sniedzejs;
    }

    private function sendReservationInvoiceEmails(Rezervacija $rezervacija, Maksajums $maksajums): void
    {
        $clientEmail = $rezervacija->klients?->persona?->epasts;
        $providerEmail = $rezervacija->transportlidzeklis?->sniedzejs?->persona?->epasts;

        try {
            if ($clientEmail) {
                Mail::to($clientEmail)->send(new ReservationInvoiceMail($rezervacija, $maksajums, 'client'));
            }

            if ($providerEmail) {
                Mail::to($providerEmail)->send(new ReservationInvoiceMail($rezervacija, $maksajums, 'provider'));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send reservation invoice email.', [
                'rezervacija_id' => $rezervacija->rezervacija_id,
                'maksajums_id' => $maksajums->maksajums_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
