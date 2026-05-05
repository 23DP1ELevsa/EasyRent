<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Klients;
use App\Models\PakalpojumuSniedzejs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request, $personaId)
    {
        $persona = $this->resolveAuthorizedPersona($request, $personaId);
        if ($persona instanceof \Illuminate\Http\JsonResponse) {
            return $persona;
        }

        $relation = $persona->loma === 'klients' ? 'klients' : 'pakalpojumuSniedzejs';

        return response()->json([
            'persona' => $persona->load($relation),
        ]);
    }

    public function update(Request $request, $personaId)
    {
        $persona = $this->resolveAuthorizedPersona($request, $personaId);
        if ($persona instanceof \Illuminate\Http\JsonResponse) {
            return $persona;
        }

        // Check if trying to change loma or email
        if ($request->has('loma')) {
            return response()->json([
                'message' => 'Lomu mainīt nevar',
                'errors' => ['loma' => ['Lomu mainīt nevar.']]
            ], 422);
        }

        if ($request->has('epasts') || $request->has('email')) {
            return response()->json([
                'message' => 'E-pastu mainīt nevar',
                'errors' => ['epasts' => ['E-pastu mainīt nevar.']]
            ], 422);
        }

        // Build validation rules dynamically
        $rules = [
            'vards' => ['sometimes', 'string', 'min:2', 'max:50'],
            'uzvards' => ['sometimes', 'string', 'max:50'],
            'kontakttalrunis' => ['sometimes', 'nullable', 'string', 'min:6', 'max:20'],
            'bankas_konts' => ['sometimes', 'nullable', 'string', 'min:15', 'max:34'],
            'password' => ['sometimes', 'string', Password::min(8)->mixedCase()->symbols()],
            'registracijas_numurs' => ['sometimes', 'string', 'max:20'],
            'iela' => ['sometimes', 'string', 'max:150'],
            'majas_numurs' => ['sometimes', 'string', 'max:20'],
            'dzivokla_numurs' => ['sometimes', 'nullable', 'string', 'max:20'],
            'pilseta' => ['sometimes', 'string', 'max:100'],
            'pasta_indekss' => ['sometimes', 'string', 'max:20'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];

        // Add unique rule for lietotajvards only for klients
        if ($persona->loma === 'klients') {
            $klients = Klients::where('persona_id', $persona->persona_id)->first();
            $rules['lietotajvards'] = [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('klients', 'lietotajvards')->ignore($klients?->klients_id, 'klients_id')
            ];
        }

        $data = $request->validate($rules, [
            'lietotajvards.unique' => 'Lietotājvārds jau ir aizņemts.',
            'kontakttalrunis.min' => 'Tālrunim jābūt vismaz 6 simbolu garam.',
            'kontakttalrunis.max' => 'Tālruņa garums nedrīkst pārsniegt 20 simbolus.',
            'bankas_konts.min' => 'IBAN jābūt vismaz 15 simbolu garam.',
            'bankas_konts.max' => 'IBAN nedrīkst būt garāks par 34 simboliem.',
            'password.min' => 'Parolei jābūt vismaz 8 simbolu garai.',
            'password.mixed' => 'Parolei jāsatur vismaz viens lielais burts.',
            'password.symbols' => 'Parolei jāsatur vismaz viens speciālais simbols.',
        ]);

        // Update persona fields (only those that are present)
        $updateData = [];
        if (isset($data['vards'])) $updateData['vards'] = $data['vards'];
        if (isset($data['uzvards'])) $updateData['uzvards'] = $data['uzvards'];
        if (isset($data['kontakttalrunis'])) $updateData['kontakttalrunis'] = $data['kontakttalrunis'];
        if (isset($data['bankas_konts'])) $updateData['bankas_konts'] = $data['bankas_konts'];
        if (isset($data['password'])) $updateData['parole'] = Hash::make($data['password']);

        if (!empty($updateData)) {
            $persona->update($updateData);
        }

        // Update klients or PakalpojumuSniedzejs
        if ($persona->loma === 'klients') {
            $klients = Klients::where('persona_id', $persona->persona_id)->first();
            if ($klients && isset($data['lietotajvards'])) {
                $klients->update(['lietotajvards' => $data['lietotajvards']]);
            }
        } else {
            $sniedzejs = PakalpojumuSniedzejs::where('persona_id', $persona->persona_id)->first();
            if ($sniedzejs) {
                $updateSniedzejs = [];
                if (isset($data['registracijas_numurs'])) $updateSniedzejs['registracijas_numurs'] = $data['registracijas_numurs'];
                if (isset($data['iela'])) $updateSniedzejs['iela'] = $data['iela'];
                if (isset($data['majas_numurs'])) $updateSniedzejs['majas_numurs'] = $data['majas_numurs'];
                if (array_key_exists('dzivokla_numurs', $data)) $updateSniedzejs['dzivokla_numurs'] = $this->normalizeOptionalString($data['dzivokla_numurs']);
                if (isset($data['pilseta'])) $updateSniedzejs['pilseta'] = $data['pilseta'];
                if (isset($data['pasta_indekss'])) $updateSniedzejs['pasta_indekss'] = $data['pasta_indekss'];
                if (array_key_exists('latitude', $data)) $updateSniedzejs['latitude'] = $data['latitude'];
                if (array_key_exists('longitude', $data)) $updateSniedzejs['longitude'] = $data['longitude'];
                if (!empty($updateSniedzejs)) {
                    $sniedzejs->update($updateSniedzejs);
                }
            }
        }

        $relation = $persona->loma === 'klients' ? 'klients' : 'pakalpojumuSniedzejs';

        return response()->json([
            'persona' => $persona->load($relation),
            'message' => 'Profils atjaunināts',
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function resolveAuthorizedPersona(Request $request, int|string $personaId): Persona|\Illuminate\Http\JsonResponse
    {
        $persona = $request->user();

        if (!$persona) {
            return response()->json(['message' => 'Nepieciešama autentifikācija.'], 401);
        }

        if ((int) $persona->persona_id !== (int) $personaId) {
            return response()->json(['message' => 'Nav atļauts piekļūt cita lietotāja profilam.'], 403);
        }

        return $persona;
    }
}

