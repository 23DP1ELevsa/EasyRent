<?php

namespace App\Http\Controllers;

use App\Models\LietotajaIerobezojums as Restriction;
use App\Models\Persona;
use App\Models\TransportlidzeklaFoto;
use App\Models\Transportlidzeklis;
use App\Support\AccountRestrictions;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehiclePhotoController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        return response()->json($this->vehicle($request, $id)->photos()->galleryOrder()->get(['foto_id']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id, int $photo): Response
    {
        $image = $this->vehicle($request, $id)->photos()->findOrFail($photo);

        return $this->imageResponse($image);
    }

    public function publicIndex(int $id): JsonResponse
    {
        $vehicle = $this->publicVehicle($id)->first();
        abort_unless($vehicle, 404, '', ['Cache-Control' => 'no-store']);

        return response()->json($vehicle->photos()->galleryOrder()->get(['foto_id', 'transportlidzeklis_id', 'content_hash'])->map(fn ($photo) => [
            'foto_id' => $photo->getKey(),
            'url' => $photo->publicUrl(),
        ]))->header('Cache-Control', 'no-store');
    }

    public function publicShow(Request $request, int $id, int $photo): Response
    {
        $image = TransportlidzeklaFoto::whereIn('transportlidzeklis_id', $this->publicVehicle($id)->select('transportlidzeklis_id'))
            ->find($photo);
        abort_unless($image, 404, '', ['Cache-Control' => 'no-store']);

        $response = $this->imageResponse($image);
        $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');
        $response->setEtag($image->content_hash ?? hash('sha256', $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    private function imageResponse(TransportlidzeklaFoto $image): Response
    {
        return response(base64_decode($image->attels), 200, [
            'Content-Type' => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        return $this->write($request, $id, function (Transportlidzeklis $vehicle) use ($request) {
            $data = $request->validate(TransportlidzeklaFoto::uploadRules(), TransportlidzeklaFoto::uploadMessages());
            if ($vehicle->photos()->count() + count($data['photos']) > TransportlidzeklaFoto::LIMIT) {
                throw ValidationException::withMessages(['photos' => __('admin.photo_limit')]);
            }

            $photos = array_map(function ($file) use ($vehicle) {
                $image = $vehicle->photos()->make(TransportlidzeklaFoto::attributesFromUpload($file));
                $this->save($image);

                return ['foto_id' => $image->getKey()];
            }, $data['photos']);
            return response()->json($photos, 201);
        });
    }

    public function replace(Request $request, int $id, int $photo): Response
    {
        return $this->write($request, $id, function (Transportlidzeklis $vehicle) use ($request, $photo) {
            $image = $vehicle->photos()->findOrFail($photo);
            $data = $request->validate(['photo' => TransportlidzeklaFoto::fileRules()], TransportlidzeklaFoto::fileMessages('photo'));
            $image->fill(TransportlidzeklaFoto::attributesFromUpload($data['photo']));
            $this->save($image);

            return response()->noContent();
        });
    }

    public function makePrimary(Request $request, int $id, int $photo): Response
    {
        return $this->write($request, $id, function (Transportlidzeklis $vehicle) use ($photo) {
            $image = $vehicle->photos()->select('foto_id', 'position')->findOrFail($photo);
            $vehicle->photos()->whereKeyNot($photo)->where('position', 0)->update(['position' => 1]);
            $image->position = 0;
            $this->save($image);

            return response()->noContent();
        });
    }

    public function destroy(Request $request, int $id, int $photo): Response
    {
        return $this->write($request, $id, function (Transportlidzeklis $vehicle) use ($photo) {
            $image = $vehicle->photos()->select('foto_id')->findOrFail($photo);
            if ($vehicle->photos()->count() <= 1) {
                throw ValidationException::withMessages(['photos' => __('vehicle.last_photo')]);
            }
            $image->delete();

            return response()->noContent();
        });
    }

    private function write(Request $request, int $id, Closure $operation): Response|JsonResponse
    {
        $actor = $request->user();

        return DB::transaction(function () use ($request, $id, $operation, $actor) {
            $account = Persona::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            AccountRestrictions::assertCurrent($actor, $account);
            AccountRestrictions::assertAllowed($account, Restriction::LISTING);

            return $operation($this->vehicle($request, $id, ownerWrite: true));
        }, 3);
    }

    private function save(TransportlidzeklaFoto $image): void
    {
        if (! $image->save()) {
            throw ValidationException::withMessages(['photos' => __('vehicle.photo_save_failed')]);
        }
    }

    private function publicVehicle(int $id): Builder
    {
        return Transportlidzeklis::listed()->whereKey($id)->where('statuss', '!=', 'neaktivs');
    }

    private function vehicle(Request $request, int $id, bool $ownerWrite = false): Transportlidzeklis
    {
        if (! $ownerWrite && $request->routeIs('admin.vehicle.photos.*')) {
            return Transportlidzeklis::findOrFail($id);
        }

        $owner = $request->user()->pakalpojumuSniedzejs;
        abort_unless($owner, 403, __('app.providerOnly'));
        $query = $owner->transportlidzekli()->whereNull('archived_at');
        if ($ownerWrite) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }
}
