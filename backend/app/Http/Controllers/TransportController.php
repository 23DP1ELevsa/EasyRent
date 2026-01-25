<?php

namespace App\Http\Controllers;

use App\Models\Transportlidzeklis;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $query = Transportlidzeklis::with(['sniedzejs.persona', 'veids']);
        
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
}