<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Transportlidzeklis;
use App\Support\AccountRestrictions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VehiclePhotoController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        return response()->json($this->vehicle($request, $id)->photos()->orderBy('foto_id')->get(['foto_id']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id, int $photo): Response
    {
        $image = $this->vehicle($request, $id)->photos()->findOrFail($photo);

        return response(base64_decode($image->attels), 200, [
            'Content-Type' => $image->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:1536'],
        ]);
        $actor = $request->user();

        return DB::transaction(function () use ($request, $id, $actor, $data) {
            $account = Persona::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            AccountRestrictions::assertCurrent($actor, $account);
            AccountRestrictions::assertUnblocked($account);
            $vehicle = $this->vehicle($request, $id, ownerWrite: true);
            abort_if($vehicle->photos()->count() + count($data['photos']) > 4, 422, __('admin.photo_limit'));

            $photos = [];
            foreach ($data['photos'] as $file) {
                $image = $vehicle->photos()->create([
                    'mime_type' => $file->getMimeType(),
                    'attels' => base64_encode($file->getContent()),
                ]);
                $photos[] = ['foto_id' => $image->getKey()];
            }

            return response()->json($photos, 201);
        }, 3);
    }

    public function destroy(Request $request, int $id, int $photo): Response
    {
        $actor = $request->user();
        DB::transaction(function () use ($request, $id, $photo, $actor) {
            $account = Persona::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            AccountRestrictions::assertCurrent($actor, $account);
            AccountRestrictions::assertUnblocked($account);
            $this->vehicle($request, $id, ownerWrite: true)->photos()->findOrFail($photo)->delete();
        }, 3);

        return response()->noContent();
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
