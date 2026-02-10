<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Klients;
use App\Models\PakalpojumuSniedzejs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Vispirms validēt — ja neiziet, Laravel automātiski atgriež 422
        $data = $request->validate([
            'name' => ['required','string','min:2','max:255'],
            'email' => ['required','email','max:255','unique:persona,epasts'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'loma' => ['required','in:klients,pakalpojumu_sniedzejs'],
            'kontakttalrunis' => ['required', 'string', 'max:20'],
            'bankas_konts' => ['required', 'string', 'max:34'],
            'lietotajvards' => ['required_if:loma,klients', 'string', 'max:30', 'unique:klients,lietotajvards'],
            'registracijas_numurs' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'iela' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:150'],
            'majas_numurs' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'dzivokla_numurs' => ['nullable', 'string', 'max:20'],
            'pilseta' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:100'],
            'pasta_indekss' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
        ], [
            'email.unique' => 'E-pasts jau ir reģistrēts.',
            'lietotajvards.unique' => 'Lietotājvārds jau ir aizņemts.',
            'name.required' => 'Vārds un uzvārds ir obligāts.',
            'password.required' => 'Parole ir obligāta.',
            'kontakttalrunis.required' => 'Tālrunis ir obligāts.',
            'bankas_konts.required' => 'Banka konta numurs (IBAN) ir obligāts.',
        ]);

        // Pēc validācijas — saglabāt datus (validācija jau pārbaudīta)
        $nameParts = explode(' ', trim($data['name']), 2);
        $vards = $nameParts[0];
        $uzvards = $nameParts[1] ?? '';

        $persona = Persona::create([
            'vards' => $vards,
            'uzvards' => $uzvards,
            'epasts' => $data['email'],
            'parole' => Hash::make($data['password']),
            'loma' => $data['loma'],
            'kontakttalrunis' => $data['kontakttalrunis'] ?? null,
            'bankas_konts' => $data['bankas_konts'] ?? null,
        ]);

        if ($data['loma'] === 'klients') {
            Klients::create([
                'persona_id' => $persona->persona_id,
                'lietotajvards' => $data['lietotajvards'],
            ]);
        } else {
            PakalpojumuSniedzejs::create([
                'persona_id' => $persona->persona_id,
                'registracijas_numurs' => $data['registracijas_numurs'],
                'iela' => $data['iela'],
                'majas_numurs' => $data['majas_numurs'],
                'dzivokla_numurs' => $data['dzivokla_numurs'] ?? null,
                'pilseta' => $data['pilseta'],
                'pasta_indekss' => $data['pasta_indekss'],
            ]);
        }

        $token = bin2hex(random_bytes(32));

        return response()->json([
            'persona' => $persona->load($data['loma'] === 'klients' ? 'klients' : 'pakalpojumuSniedzejs'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $persona = Persona::where('epasts', $data['email'])->first();

        if (!$persona || !Hash::check($data['password'], $persona->parole)) {
            return response()->json(['message' => 'Nepareizs e-pasts vai parole'], 422);
        }

        $token = bin2hex(random_bytes(32));

        // Lai atgrieztu arī klients/pakalpojumu sniedzējs datus, jānosaka attiecība
        $relation = $persona->loma === 'klients' ? 'klients' : 'pakalpojumuSniedzejs';

        return response()->json([
            'persona' => $persona->load($relation),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        return response()->json(['message' => 'OK']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
