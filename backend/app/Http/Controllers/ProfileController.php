<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Klients;
use App\Models\PakalpojumuSniedzejs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request, $personaId)
    {
        $persona = Persona::findOrFail($personaId);

        $relation = $persona->loma === 'klients' ? 'klients' : 'pakalpojumuSniedzejs';

        return response()->json([
            'persona' => $persona->load($relation),
        ]);
    }

    public function update(Request $request, $personaId)
    {
        $persona = Persona::findOrFail($personaId);

        $data = $request->validate([
            'vards' => ['sometimes', 'string', 'min:2', 'max:50'],
            'uzvards' => ['sometimes', 'string', 'max:50'],
            'kontakttalrunis' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bankas_konts' => ['sometimes', 'nullable', 'string', 'max:34'],
            'password' => ['sometimes', 'string', 'min:8'],
            // Для klients
            'lietotajvards' => ['sometimes', 'string', 'max:30'],
            // Для pakalpojumu_sniedzejs
            'registracijas_numurs' => ['sometimes', 'string', 'max:20'],
            'atrasanas_adrese' => ['sometimes', 'string', 'max:255'],
        ]);

        // Обновляем персону
        $updateData = [];
        if (isset($data['vards'])) $updateData['vards'] = $data['vards'];
        if (isset($data['uzvards'])) $updateData['uzvards'] = $data['uzvards'];
        if (isset($data['kontakttalrunis'])) $updateData['kontakttalrunis'] = $data['kontakttalrunis'];
        if (isset($data['bankas_konts'])) $updateData['bankas_konts'] = $data['bankas_konts'];
        if (isset($data['password'])) $updateData['parole'] = Hash::make($data['password']);

        if (!empty($updateData)) {
            $persona->update($updateData);
        }

        // Обновляем данные связанной модели
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
                if (isset($data['atrasanas_adrese'])) $updateSniedzejs['atrasanas_adrese'] = $data['atrasanas_adrese'];
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
}
