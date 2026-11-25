<?php

namespace App\Http\Controllers;

use App\Models\PakalpojumuSniedzejs;
use App\Models\ParkapumaZinojums;
use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\TransportliedzieklsVeids;
use App\Support\AccountRestrictions;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Models\TransportlidzeklaFoto;
use App\Models\Transportlidzeklis;
use App\Support\DrivingLicence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        Rezervacija::cleanupAvailability();

        $categories = $request->validate([
            'licence_categories' => ['sometimes', 'array', 'list', 'max:'.count(DrivingLicence::CATEGORIES)],
            'licence_categories.*' => ['required', 'string', Rule::in(DrivingLicence::CATEGORIES)],
        ], DrivingLicence::messages('licence_categories.*'))['licence_categories'] ?? [];

        $query = Transportlidzeklis::listed()->withPhotoSummary()->with(['sniedzejs.persona', 'veids', 'rezervacijas' => $this->availability()])
            ->withAvg(['atsauksmes as videjais_vertejums' => fn ($query) => $query->visible()], 'vertejums')
            ->withCount(['atsauksmes as atsauksmju_skaits' => fn ($query) => $query->visible()]);
        
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
        if ($categories !== []) {
            $query->whereIn(DrivingLicence::FIELD, $categories);
        }

        return response()->json($query->get()->each($this->exposePublicVehicle(...)))
            ->header('Cache-Control', 'no-store');
    }

    public function show($id)
    {
        Rezervacija::cleanupAvailability();

        $transport = Transportlidzeklis::listed()->withPhotoSummary()->with([
            'sniedzejs.persona',
            'veids',
            'rezervacijas' => $this->availability(),
            'atsauksmes' => fn ($query) => $query->visible()->with('klients.persona'),
        ])
            ->withAvg(['atsauksmes as videjais_vertejums' => fn ($query) => $query->visible()], 'vertejums')
            ->withCount(['atsauksmes as atsauksmju_skaits' => fn ($query) => $query->visible()])
            ->find($id);
        
        if (!$transport) {
            return response()->json(['error' => __('app.notFound')], 404)->header('Cache-Control', 'no-store');
        }

        $this->exposePublicVehicle($transport);

        return response()->json($transport)->header('Cache-Control', 'no-store');
    }

    private function exposePublicVehicle(Transportlidzeklis $transport): void
    {
        $photo = $transport->primaryPhoto;
        $transport->setAttribute('primary_photo_url', $transport->statuss === 'neaktivs' ? null : $photo?->publicUrl());
        $transport->unsetRelation('primaryPhoto');

        $provider = $transport->sniedzejs;
        if (!$provider) {
            $transport->makeHidden('adrese');

            return;
        }
        $location = $provider->publicPickupLocation();

        $transport->setAttribute('pickup_location', $location);
        $transport->setAttribute('adrese', implode(', ', array_filter([$location['address'], $location['city']])));
        $provider->setAttribute('iela', $provider->publicStreet());
        $provider->makeHidden('dzivokla_numurs');
    }

    public function store(Request $request)
    {
        return DB::transaction(fn () => $this->storeForProvider($request), 3);
    }

    private function storeForProvider(Request $request)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        AccountRestrictions::assertAllowed($request->user(), Restriction::LISTING);
        $data = $request->validate([
            'veids_id' => ['required', 'integer', 'exists:transportlidzekla_veids,veids_id'],
            'marka' => ['required', 'string', 'max:50'],
            'modelis' => ['required', 'string', 'max:50'],
            'atrumkarba' => ['required', 'in:Automāts,Mehānika,-'],
            'degvielas_veids' => ['required', 'in:Benzīns,Dīzelis,Elektro,-'],
            'dienas_nomas_cena' => ['required', 'numeric', 'min:0'],
            'statuss' => ['required', 'in:pieejams,aiznemts,neaktivs'],
            'registracijas_numurs' => ['required', 'string', 'max:20', 'unique:transportlidzeklis,registracijas_numurs'],
            DrivingLicence::FIELD => ['required', 'string', Rule::in(DrivingLicence::CATEGORIES)],
            'primary_photo_index' => ['sometimes', 'required', 'integer', 'min:0', 'max:'.(TransportlidzeklaFoto::LIMIT - 1)],
        ] + TransportlidzeklaFoto::uploadRules(), DrivingLicence::messages() + TransportlidzeklaFoto::uploadMessages() + [
            'primary_photo_index.*' => __('vehicle.primary_invalid'),
        ]);

        $photos = $data['photos'];
        $primaryIndex = (int) ($data['primary_photo_index'] ?? 0);
        if (! isset($photos[$primaryIndex])) {
            throw ValidationException::withMessages(['primary_photo_index' => __('vehicle.primary_invalid')]);
        }
        unset($data['photos'], $data['primary_photo_index']);

        $this->requireActiveCategory($data['veids_id']);
        $data['sniedzejs_id'] = $provider->sniedzejs_id;
        $data['adrese'] = $this->buildProviderAddress($provider);

        $transport = Transportlidzeklis::create($data);
        foreach ($photos as $index => $file) {
            $photo = $transport->photos()->make(TransportlidzeklaFoto::attributesFromUpload($file));
            $photo->position = $index === $primaryIndex ? 0 : 1;
            if (! $photo->save()) {
                throw ValidationException::withMessages(['photos' => __('vehicle.photo_save_failed')]);
            }
        }

        return response()->json($transport->load(['sniedzejs.persona', 'veids']), 201);
    }

    public function update(Request $request, $id)
    {
        return DB::transaction(fn () => $this->updateForProvider($request, $id), 3);
    }

    private function updateForProvider(Request $request, $id)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        $transport = Transportlidzeklis::whereNull('archived_at')->lockForUpdate()->find($id);

        if (!$transport) {
            return response()->json(['error' => __('app.notFound')], 404);
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
            DrivingLicence::FIELD => ['sometimes', 'required', 'string', Rule::in(DrivingLicence::CATEGORIES)],
        ], DrivingLicence::messages());

        if ((int) $provider->sniedzejs_id !== (int) $transport->sniedzejs_id) {
            return response()->json(['message' => __('app.vehicleEditForbidden')], 403);
        }

        if ($request->hasAny(['marka', 'modelis', 'atrumkarba', 'degvielas_veids', 'dienas_nomas_cena', 'statuss', 'veids_id', 'registracijas_numurs'])) {
            $data['adrese'] = $this->buildProviderAddress($provider);
        }

        $publishing = $transport->statuss === 'neaktivs' && isset($data['statuss']) && $data['statuss'] !== 'neaktivs';
        if ($publishing) {
            AccountRestrictions::assertAllowed($request->user(), Restriction::LISTING);
        }
        if ($publishing || (isset($data['veids_id']) && (int) $data['veids_id'] !== (int) $transport->veids_id)) {
            $this->requireActiveCategory($data['veids_id'] ?? $transport->veids_id);
        }

        if ($publishing) {
            $errors = [];
            if (($data[DrivingLicence::FIELD] ?? $transport->{DrivingLicence::FIELD}) === null) {
                $errors[DrivingLicence::FIELD] = __('vehicle.licence_required');
            }
            if (! $transport->photos()->exists()) {
                $errors['photos'] = __('vehicle.photo_required');
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $transport->update($data);

        return response()->json($transport->load(['sniedzejs.persona', 'veids', 'rezervacijas' => $this->availability()]));
    }

    public function destroy(Request $request, $id)
    {
        Rezervacija::cleanupAvailability();

        return DB::transaction(fn () => $this->destroyForProvider($request, $id), 3);
    }

    private function destroyForProvider(Request $request, $id)
    {
        $provider = $this->resolveAuthorizedProvider($request);
        if ($provider instanceof \Illuminate\Http\JsonResponse) {
            return $provider;
        }

        $transport = Transportlidzeklis::whereNull('archived_at')->lockForUpdate()->find($id);

        if (!$transport) {
            return response()->json(['error' => __('app.notFound')], 404);
        }

        if ((int) $provider->sniedzejs_id !== (int) $transport->sniedzejs_id) {
            return response()->json(['message' => __('app.vehicleDeleteForbidden')], 403);
        }

        $hasActiveReservations = Rezervacija::where('transportlidzeklis_id', $transport->transportlidzeklis_id)
            ->where('beigu_laiks', '>', now())
            ->exists();

        if ($hasActiveReservations) {
            return response()->json([
                'message' => __('app.vehicleHasBooking'),
            ], 409);
        }

        if ($transport->moderation_hidden_at || $transport->rezervacijas()->exists() || $transport->atsauksmes()->exists()
            || ParkapumaZinojums::where('objekta_veids', 'vehicle')->where('objekta_id', $id)->lockForUpdate()->first(['zinojums_id'])) {
            $transport->forceFill(['archived_at' => now(), 'statuss' => 'neaktivs'])->save();
        } else {
            $transport->delete();
        }

        return response()->json(['message' => __('app.vehicleDeleted')]);
    }

    private function requireActiveCategory(int $id): void
    {
        $category = TransportliedzieklsVeids::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($category->deactivated_at !== null) {
            throw ValidationException::withMessages(['veids_id' => __('admin.inactive_category')]);
        }
    }

    private function availability(): \Closure
    {
        return fn ($query) => $query->where('beigu_laiks', '>', now())
            ->select('rezervacija_id', 'transportlidzeklis_id', 'sakuma_laiks', 'beigu_laiks', 'apmaksas_statuss');
    }

    private function buildProviderAddress(PakalpojumuSniedzejs $provider): string
    {
        $street = trim((string) ($provider->iela ?? ''));
        $houseNo = trim((string) ($provider->majas_numurs ?? ''));
        $apartmentNo = trim((string) ($provider->dzivokla_numurs ?? ''));
        $city = trim((string) ($provider->pilseta ?? ''));

        if ($street === '' || $houseNo === '' || $city === '') {
            throw ValidationException::withMessages([
                'adrese' => [__('app.vehicleAddressRequired')],
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
        $persona = Persona::whereKey($request->user()->getKey())->lockForUpdate()->first();

        if (!$persona) {
            return response()->json(['message' => __('app.authenticationRequired')], 401);
        }

        if ($persona->loma !== 'pakalpojumu_sniedzejs' || !$persona->pakalpojumuSniedzejs) {
            return response()->json(['message' => __('app.providerOnly')], 403);
        }

        AccountRestrictions::assertUnblocked($persona);
        AccountRestrictions::assertCurrent($request->user(), $persona);

        return $persona->pakalpojumuSniedzejs;
    }
}
