<?php

namespace App\Http\Controllers;

use App\Models\PakalpojumuSniedzejs;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransportController extends Controller
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

        $query = Transportlidzeklis::with(['sniedzejs.persona', 'veids', 'rezervacijas'])
            ->withAvg('atsauksmes as videjais_vertejums', 'vertejums')
            ->withCount('atsauksmes as atsauksmju_skaits');
        
        // Filtri
        if ($request->has('veids_id')) {
            $query->where('veids_id', $request->veids_id);
        }
        if ($request->has('statuss')) {
            $query->where('statuss', $request->statuss);
        }
        if ($request->has('min_cena')) {
            $query->where('dienas_nomas_cena', '>=', $request->min_cena);
        }
        if ($request->has('max_cena')) {
            $query->where('dienas_nomas_cena', '<=', $request->max_cena);
        }
        if ($request->has('sniedzejs_id')) {
            $query->where('sniedzejs_id', $request->sniedzejs_id);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $this->cleanupExpiredUnpaidReservations();

        $transport = Transportlidzeklis::with([
            'sniedzejs.persona',
            'veids',
            'rezervacijas',
            'atsauksmes.klients.persona',
        ])
            ->withAvg('atsauksmes as videjais_vertejums', 'vertejums')
            ->withCount('atsauksmes as atsauksmju_skaits')
            ->find($id);
        
        if (!$transport) {
            return response()->json(['error' => 'Nav atrasts'], 404);
        }

        return response()->json($transport);
    }

    public function store(Request $request)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        $data = $request->validate([
            'veids_id' => ['required', 'integer', 'exists:transportlidzekla_veids,veids_id'],
            'marka' => ['required', 'string', 'max:50'],
            'modelis' => ['required', 'string', 'max:50'],
            'atrumkarba' => ['required', 'in:Automāts,Mehānika,-'],
            'degvielas_veids' => ['required', 'in:Benzīns,Dīzelis,Elektro,-'],
            'dienas_nomas_cena' => ['required', 'numeric', 'min:0'],
            'statuss' => ['required', 'in:pieejams,aiznemts,neaktivs'],
            'registracijas_numurs' => ['required', 'string', 'max:20', 'unique:transportlidzeklis,registracijas_numurs'],
        ]);

        $data['sniedzejs_id'] = $provider->sniedzejs_id;
        $data['adrese'] = $this->buildProviderAddress($provider);

        $transport = Transportlidzeklis::create($data);

        return response()->json($transport->load(['sniedzejs.persona', 'veids']), 201);
    }

    public function update(Request $request, $id)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        $transport = Transportlidzeklis::find($id);

        if (!$transport) {
            return response()->json(['error' => 'Nav atrasts'], 404);
        }

        $data = $request->validate([
            'veids_id' => ['sometimes', 'integer', 'exists:transportlidzekla_veids,veids_id'],
            'marka' => ['sometimes', 'string', 'max:50'],
            'modelis' => ['sometimes', 'string', 'max:50'],
            'atrumkarba' => ['sometimes', 'in:Automāts,Mehānika,-'],
            'degvielas_veids' => ['sometimes', 'in:Benzīns,Dīzelis,Elektro,-'],
            'dienas_nomas_cena' => ['sometimes', 'numeric', 'min:0'],
            'statuss' => ['sometimes', 'in:pieejams,aiznemts,neaktivs'],
            'registracijas_numurs' => ['sometimes', 'string', 'max:20', 'unique:transportlidzeklis,registracijas_numurs,' . $transport->transportlidzeklis_id . ',transportlidzeklis_id'],
        ]);

        if ((int) $provider->sniedzejs_id !== (int) $transport->sniedzejs_id) {
            return response()->json(['message' => 'Nav atļauts mainīt cita sniedzēja transportu'], 403);
        }

        if ($request->hasAny(['marka', 'modelis', 'atrumkarba', 'degvielas_veids', 'dienas_nomas_cena', 'statuss', 'veids_id', 'registracijas_numurs'])) {
            $data['adrese'] = $this->buildProviderAddress($provider);
        }

        $transport->update($data);

        return response()->json($transport->load(['sniedzejs.persona', 'veids', 'rezervacijas']));
    }

    public function destroy(Request $request, $id)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        $transport = Transportlidzeklis::find($id);

        if (!$transport) {
            return response()->json(['error' => 'Nav atrasts'], 404);
        }

        if ((int) $provider->sniedzejs_id !== (int) $transport->sniedzejs_id) {
            return response()->json(['message' => 'Nav atļauts dzēst cita sniedzēja transportu.'], 403);
        }

        $hasActiveReservations = Rezervacija::where('transportlidzeklis_id', $transport->transportlidzeklis_id)
            ->where('beigu_laiks', '>', Carbon::now())
            ->exists();

        if ($hasActiveReservations) {
            return response()->json([
                'message' => 'Transportlīdzekli nevar dzēst, kamēr tam ir aktīva vai gaidāma rezervācija.',
            ], 409);
        }

        $transport->delete();

        return response()->json(['message' => 'Transportlīdzeklis dzēsts.']);
    }

    private function buildProviderAddress(PakalpojumuSniedzejs $provider): string
    {
        $street = trim((string) ($provider->iela ?? ''));
        $houseNo = trim((string) ($provider->majas_numurs ?? ''));
        $apartmentNo = trim((string) ($provider->dzivokla_numurs ?? ''));
        $city = trim((string) ($provider->pilseta ?? ''));

        if ($street === '' || $houseNo === '' || $city === '') {
            throw ValidationException::withMessages([
                'adrese' => ['Lai pievienotu transportu, profilā jānorāda pilna adrese (iela, mājas numurs, pilsēta).'],
            ]);
        }

        $addressLine = $street . ' ' . $houseNo;
        if ($apartmentNo !== '') {
            $addressLine .= '-' . $apartmentNo;
        }

        return $addressLine . ', ' . $city;
    }

    private function resolveAuthorizedProvider(Request $request): PakalpojumuSniedzejs|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user()?->load('pakalpojumuSniedzejs');

        if (!$persona) {
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
        }

        if ($persona->loma !== 'pakalpojumu_sniedzejs' || !$persona->pakalpojumuSniedzejs) {
            return response()->json(['message' => 'Šī darbība pieejama tikai pakalpojumu sniedzējam.'], 403);
        }

        return $persona->pakalpojumuSniedzejs;
    }
}