<?php

namespace App\Http\Controllers;

use App\Models\TransportliedzieklsVeids;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransportVeidsController extends Controller
{
    public function index()
    {
        return response()->json(TransportliedzieklsVeids::orderBy('nosaukums')->get());
    }

    public function store(Request $request)
    {
        if ($response = $this->ensureProvider($request)) {
            return $response;
        }

        $data = $request->validate([
            'nosaukums' => ['required', 'string', 'max:50', 'unique:transportlidzekla_veids,nosaukums'],
            'tips' => ['nullable', 'string', 'max:50'],
        ]);

        $data['nosaukums'] = trim($data['nosaukums']);

        if (empty($data['tips'])) {
            $data['tips'] = Str::slug($data['nosaukums'], '_');
        } else {
            $data['tips'] = Str::slug(trim($data['tips']), '_');
        }

        if ($data['tips'] === '') {
            $data['tips'] = Str::slug(Str::ascii($data['nosaukums']), '_');
        }

        if ($data['tips'] === '') {
            $data['tips'] = 'transporta_veids';
        }

        $veids = TransportliedzieklsVeids::create($data);

        return response()->json($veids, 201);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->ensureProvider($request)) {
            return $response;
        }

        $veids = TransportliedzieklsVeids::find($id);

        if (!$veids) {
            return response()->json(['message' => 'Transporta veids nav atrasts.'], 404);
        }

        $data = $request->validate([
            'nosaukums' => [
                'required',
                'string',
                'max:50',
                Rule::unique('transportlidzekla_veids', 'nosaukums')->ignore($veids->veids_id, 'veids_id'),
            ],
            'tips' => ['nullable', 'string', 'max:50'],
        ]);

        $data['nosaukums'] = trim($data['nosaukums']);

        if (empty($data['tips'])) {
            $data['tips'] = Str::slug($data['nosaukums'], '_');
        } else {
            $data['tips'] = Str::slug(trim($data['tips']), '_');
        }

        if ($data['tips'] === '') {
            $data['tips'] = Str::slug(Str::ascii($data['nosaukums']), '_');
        }

        if ($data['tips'] === '') {
            $data['tips'] = 'transporta_veids';
        }

        $veids->update($data);

        return response()->json($veids->refresh());
    }

    private function ensureProvider(Request $request): ?\Illuminate\Http\JsonResponse
    {
        /** @var Persona|null $persona */
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
        }

        if ($persona->loma !== 'pakalpojumu_sniedzejs') {
            return response()->json(['message' => 'Šī darbība pieejama tikai pakalpojumu sniedzējam.'], 403);
        }

        return null;
    }
}
