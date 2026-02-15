<?php

namespace App\Http\Controllers;

use App\Models\Transportlidzeklis;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $query = Transportlidzeklis::with(['sniedzejs.persona', 'veids', 'rezervacijas']);
        
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

        return response()->json($query->get());
    }

    public function show($id)
    {
        $transport = Transportlidzeklis::with(['sniedzejs.persona', 'veids', 'rezervacijas', 'atsauksmes'])->find($id);
        
        if (!$transport) {
            return response()->json(['error' => 'Nav atrasts'], 404);
        }

        return response()->json($transport);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sniedzejs_id' => ['required', 'integer', 'exists:pakalpojumu_sniedzejs,sniedzejs_id'],
            'veids_id' => ['required', 'integer', 'exists:transportlidzekla_veids,veids_id'],
            'marka' => ['required', 'string', 'max:50'],
            'modelis' => ['required', 'string', 'max:50'],
            'atrumkarba' => ['nullable', 'string', 'max:20'],
            'degvielas_veids' => ['nullable', 'string', 'max:20'],
            'dienas_nomas_cena' => ['required', 'numeric', 'min:0'],
            'adrese' => ['required', 'string', 'max:255'],
            'statuss' => ['required', 'in:pieejams,aiznemts,neaktivs'],
            'registracijas_numurs' => ['required', 'string', 'max:20', 'unique:transportlidzeklis,registracijas_numurs'],
        ]);

        $transport = Transportlidzeklis::create($data);

        return response()->json($transport->load(['sniedzejs.persona', 'veids']), 201);
    }

    public function update(Request $request, $id)
    {
        $transport = Transportlidzeklis::find($id);

        if (!$transport) {
            return response()->json(['error' => 'Nav atrasts'], 404);
        }

        $data = $request->validate([
            'sniedzejs_id' => ['sometimes', 'integer', 'exists:pakalpojumu_sniedzejs,sniedzejs_id'],
            'veids_id' => ['sometimes', 'integer', 'exists:transportlidzekla_veids,veids_id'],
            'marka' => ['sometimes', 'string', 'max:50'],
            'modelis' => ['sometimes', 'string', 'max:50'],
            'atrumkarba' => ['sometimes', 'nullable', 'string', 'max:20'],
            'degvielas_veids' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dienas_nomas_cena' => ['sometimes', 'numeric', 'min:0'],
            'adrese' => ['sometimes', 'string', 'max:255'],
            'statuss' => ['sometimes', 'in:pieejams,aiznemts,neaktivs'],
            'registracijas_numurs' => ['sometimes', 'string', 'max:20', 'unique:transportlidzeklis,registracijas_numurs,' . $transport->transportlidzeklis_id . ',transportlidzeklis_id'],
        ]);

        if (isset($data['sniedzejs_id']) && $data['sniedzejs_id'] !== $transport->sniedzejs_id) {
            return response()->json(['message' => 'Nav atļauts mainīt cita sniedzēja transportu'], 403);
        }

        $transport->update($data);

        return response()->json($transport->load(['sniedzejs.persona', 'veids', 'rezervacijas']));
    }
}