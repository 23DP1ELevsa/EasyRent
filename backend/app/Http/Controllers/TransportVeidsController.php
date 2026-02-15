<?php

namespace App\Http\Controllers;

use App\Models\TransportliedzieklsVeids;
use Illuminate\Http\Request;

class TransportVeidsController extends Controller
{
    public function index()
    {
        return response()->json(TransportliedzieklsVeids::orderBy('nosaukums')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nosaukums' => ['required', 'string', 'max:50', 'unique:transportlidzekla_veids,nosaukums'],
        ]);

        $veids = TransportliedzieklsVeids::create($data);

        return response()->json($veids, 201);
    }
}
