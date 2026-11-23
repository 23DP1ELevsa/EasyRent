<?php

namespace App\Http\Controllers;

use App\Mail\ReservationInvoiceMail;
use App\Models\Maksajums;
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
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
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
            return response()->json(['message' => 'Šī darbība nav pieejama šai lomai.'], 403);
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

        return $this->withLockedVehicle($klients, $data['transportlidzeklis_id'], function (Transportlidzeklis $transport) use ($klients, $start, $end) {
            abort_if($transport->archived_at || $transport->statuss !== 'pieejams', 409, 'Transportlīdzeklis nav pieejams.');
            abort_if($start->lessThanOrEqualTo(Carbon::now()), 422, 'Sākuma laikam jābūt nākotnē.');
            abort_if($end->lessThanOrEqualTo($start), 422, 'Beigu laikam jābūt pēc sākuma laika.');

            $conflict = $transport->rezervacijas()->where('sakuma_laiks', '<', $end)->where('beigu_laiks', '>', $start)->exists();
            abort_if($conflict, 409, 'Transportlīdzeklis vairs nav pieejams izvēlētajā laikā.');

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

            return response()->json($reservation->load(['transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona']), 201);
        });
    }

    public function pay(Request $request, $id)
    {
        Rezervacija::cleanupAvailability();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $reservation = Rezervacija::findOrFail($id);
        abort_unless((int) $reservation->klients_id === (int) $klients->getKey(), 403, 'Nav atļauts apmaksāt šo rezervāciju.');

        [$rezervacija, $maksajums] = $this->withLockedVehicle($klients, $reservation->transportlidzeklis_id, function (Transportlidzeklis $transport) use ($id) {
            $reservation = $transport->rezervacijas()->lockForUpdate()->findOrFail($id);
            abort_if($reservation->apmaksas_statuss === 'apmaksata', 409, 'Rezervācija jau apmaksāta.');
            abort_if($reservation->maksajumi()->exists(), 409, 'Rezervācijai jau ir maksājuma ieraksts.');
            abort_if(now()->greaterThanOrEqualTo($reservation->sakuma_laiks), 409, 'Rezervācijas apmaksas termiņš ir beidzies.');

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
        });
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
        abort_unless($participant, 404, 'Rēķins nav atrasts.');

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
        abort_unless((int) $reservation->klients_id === (int) $klients->getKey(), 403, 'Nav atļauts atcelt šo rezervāciju.');

        return $this->withLockedVehicle($klients, $reservation->transportlidzeklis_id, function (Transportlidzeklis $transport) use ($id) {
            $reservation = $transport->rezervacijas()->lockForUpdate()->findOrFail($id);
            abort_if($reservation->apmaksas_statuss === 'apmaksata' || $reservation->maksajumi()->exists(), 409, 'Rezervāciju ar maksājuma ierakstu atcelt nevar.');
            $reservation->delete();

            return response()->json(['message' => 'Rezervācija atcelta.']);
        });
    }

    private function withLockedVehicle(Klients $client, int $vehicleId, Closure $operation): mixed
    {
        $vehicle = Transportlidzeklis::with('sniedzejs')->findOrFail($vehicleId);

        return DB::transaction(function () use ($client, $vehicle, $operation) {
            Persona::lockActiveAccounts($client->persona_id, $vehicle->sniedzejs->persona_id);

            return $operation(Transportlidzeklis::whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail());
        }, 3);
    }

    private function resolveAuthorizedClient(Request $request): Klients|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
        }

        if ($persona->loma !== 'klients') {
            return response()->json(['message' => 'Šī darbība pieejama tikai klientam.'], 403);
        }

        $klients = $persona->klients;
        if (!$klients) {
            return response()->json(['message' => 'Klienta profils nav atrasts.'], 403);
        }

        return $klients;
    }

    private function resolveAuthorizedProvider(Request $request): PakalpojumuSniedzejs|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
        }

        if ($persona->loma !== 'pakalpojumu_sniedzejs') {
            return response()->json(['message' => 'Šī darbība pieejama tikai pakalpojumu sniedzējam.'], 403);
        }

        $sniedzejs = $persona->pakalpojumuSniedzejs ?? $persona->pakalpojumu_sniedzejs;
        if (!$sniedzejs) {
            return response()->json(['message' => 'Pakalpojumu sniedzēja profils nav atrasts.'], 403);
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
