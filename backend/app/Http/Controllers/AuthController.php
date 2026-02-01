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
        $data = $request->validate([
            'name' => ['required','string','min:2','max:255'],
            'email' => ['required','email','max:255','unique:persona,epasts'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'loma' => ['required','in:klients,pakalpojumu_sniedzejs'],
            // Для klients
            'lietotajvards' => ['required_if:loma,klients', 'string', 'max:30'],
            // Для pakalpojumu_sniedzejs
            'registracijas_numurs' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:20'],
            'atrasanas_adrese' => ['required_if:loma,pakalpojumu_sniedzejs', 'string', 'max:255'],
            'kontakttalrunis' => ['nullable', 'string', 'max:20'],
        ]);

        // Разделяем имя на имя и фамилию
        $nameParts = explode(' ', trim($data['name']), 2);
        $vards = $nameParts[0];
        $uzvards = $nameParts[1] ?? '';

        // Создаем персону
        $persona = Persona::create([
            'vards' => $vards,
            'uzvards' => $uzvards,
            'epasts' => $data['email'],
            'parole' => Hash::make($data['password']),
            'loma' => $data['loma'],
            'kontakttalrunis' => $data['kontakttalrunis'] ?? null,
        ]);

        // Создаем связанную запись в klients или pakalpojumu_sniedzejs
        if ($data['loma'] === 'klients') {
            Klients::create([
                'persona_id' => $persona->persona_id,
                'lietotajvards' => $data['lietotajvards'],
            ]);
        } else {
            PakalpojumuSniedzejs::create([
                'persona_id' => $persona->persona_id,
                'registracijas_numurs' => $data['registracijas_numurs'],
                'atrasanas_adrese' => $data['atrasanas_adrese'],
            ]);
        }

        // Создаем токен
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

        // Загружаем связанные данные в зависимости от роли
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
