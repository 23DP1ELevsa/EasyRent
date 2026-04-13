<?php

namespace App\Http\Controllers;

use App\Models\Maksajums;
use App\Models\Klients;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RezervacijaController extends Controller
{
    private function syncTransportStatusById(int $transportId): void
    {
        $transport = Transportlidzeklis::find($transportId);

        if (!$transport || $transport->statuss === 'neaktivs') {
            return;
        }

        $hasActivePaidReservation = Rezervacija::where('transportlidzeklis_id', $transportId)
            ->where('apmaksas_statuss', 'apmaksata')
            ->where('beigu_laiks', '>', Carbon::now())
            ->exists();

        $targetStatus = $hasActivePaidReservation ? 'aiznemts' : 'pieejams';

        if ($transport->statuss !== $targetStatus) {
            $transport->update(['statuss' => $targetStatus]);
        }
    }

    private function syncVehicleStatuses(): void
    {
        $vehicleIds = Rezervacija::query()
            ->select('transportlidzeklis_id')
            ->distinct()
            ->pluck('transportlidzeklis_id');

        foreach ($vehicleIds as $vehicleId) {
            $this->syncTransportStatusById((int) $vehicleId);
        }
    }

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

    private function cleanupExpiredUnpaidReservations(): void
    {
        Rezervacija::where('apmaksas_statuss', 'neapmaksata')
            ->where('sakuma_laiks', '<=', Carbon::now())
            ->delete();

        $this->syncVehicleStatuses();
    }

    public function index(Request $request)
    {
        $this->cleanupExpiredUnpaidReservations();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $rezervacijas = Rezervacija::with(['transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona'])
            ->where('klients_id', $klients->klients_id)
            ->orderByDesc('izveides_datums')
            ->get();

        return response()->json($rezervacijas);
    }

    public function store(Request $request)
    {
        $this->cleanupExpiredUnpaidReservations();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $data = $request->validate([
            'transportlidzeklis_id' => ['required', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
            'sakuma_laiks' => ['required', 'date'],
            'beigu_laiks' => ['required', 'date', 'after:sakuma_laiks'],
        ]);

        $transport = Transportlidzeklis::findOrFail($data['transportlidzeklis_id']);

        if ($transport->statuss !== 'pieejams') {
            return response()->json(['message' => 'Transportlīdzeklis nav pieejams.'], 409);
        }

        $start = $this->parseClientDateTime($data['sakuma_laiks'], $request);
        $end = $this->parseClientDateTime($data['beigu_laiks'], $request);

        if ($start->lessThanOrEqualTo(Carbon::now())) {
            return response()->json(['message' => 'Sākuma laikam jābūt nākotnē.'], 422);
        }

        $conflict = Rezervacija::where('transportlidzeklis_id', $transport->transportlidzeklis_id)
            ->where(function ($query) use ($start, $end) {
                $query->where('sakuma_laiks', '<', $end)
                      ->where('beigu_laiks', '>', $start);
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'Transportlīdzeklis vairs nav pieejams izvēlētajā laikā.'], 409);
        }

        $seconds = max(1, $start->diffInSeconds($end));
        $days = max(1, (int) ceil($seconds / 86400));
        $sum = $days * $transport->dienas_nomas_cena;

        $rezervacija = Rezervacija::create([
            'klients_id' => $klients->klients_id,
            'transportlidzeklis_id' => $transport->transportlidzeklis_id,
            'sakuma_laiks' => $start,
            'beigu_laiks' => $end,
            'rezervacijas_datums' => Carbon::today(),
            'izveides_datums' => Carbon::now(),
            'kopa_summa' => $sum,
            'maksajuma_datums' => null,
            'apmaksas_statuss' => 'neapmaksata',
        ]);

        return response()->json($rezervacija->load(['transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona']), 201);
    }

    public function pay(Request $request, $id)
    {
        $this->cleanupExpiredUnpaidReservations();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $rezervacija = Rezervacija::with(['transportlidzeklis'])->findOrFail($id);

        if ((int) $rezervacija->klients_id !== (int) $klients->klients_id) {
            return response()->json(['message' => 'Nav atļauts apmaksāt šo rezervāciju.'], 403);
        }

        if ($rezervacija->apmaksas_statuss === 'apmaksata') {
            return response()->json(['message' => 'Rezervācija jau apmaksāta.'], 409);
        }

        if ($rezervacija->apmaksas_statuss === 'neapmaksata' && Carbon::now()->greaterThanOrEqualTo($rezervacija->sakuma_laiks)) {
            $rezervacija->delete();
            return response()->json(['message' => 'Rezervācija automātiski atcelta, jo netika apmaksāta līdz sākuma laikam.'], 409);
        }

        $transaction = 'TX-' . strtoupper(Str::random(12));

        $maksajums = Maksajums::create([
            'rezervacija_id' => $rezervacija->rezervacija_id,
            'summa' => $rezervacija->kopa_summa,
            'statuss' => 'apstiprinats',
            'tranzakcijas_numurs' => $transaction,
            'rekins' => null,
        ]);

        $rezervacija->update([
            'apmaksas_statuss' => 'apmaksata',
            'maksajuma_datums' => Carbon::today(),
        ]);

        $this->syncTransportStatusById((int) $rezervacija->transportlidzeklis_id);

        return response()->json([
            'rezervacija' => $rezervacija->refresh(),
            'maksajums' => $maksajums,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->cleanupExpiredUnpaidReservations();

        $klients = $this->resolveAuthorizedClient($request);
        if ($klients instanceof \Illuminate\Http\JsonResponse) {
            return $klients;
        }

        $rezervacija = Rezervacija::findOrFail($id);

        if ((int) $rezervacija->klients_id !== (int) $klients->klients_id) {
            return response()->json(['message' => 'Nav atļauts atcelt šo rezervāciju.'], 403);
        }

        if ($rezervacija->apmaksas_statuss === 'apmaksata') {
            return response()->json(['message' => 'Apmaksātu rezervāciju atcelt nevar.'], 409);
        }

        $rezervacija->delete();

        return response()->json(['message' => 'Rezervācija atcelta.']);
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
}
