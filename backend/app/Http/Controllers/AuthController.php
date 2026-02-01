<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','min:2','max:100'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','confirmed'],
            'loma' => ['required', Rule::in(['klients','pakalpojumu_sniedzejs'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'loma' => $data['loma'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'loma' => $user->loma],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Nepareizs e-pasts vai parole'], 422);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'loma' => $user->loma],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'OK']);
    }
}
