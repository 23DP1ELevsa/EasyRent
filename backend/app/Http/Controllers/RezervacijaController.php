<?php

namespace App\Http\Controllers;

use App\Models\Maksajums;
use App\Models\Rezervacija;
use App\Models\Transportlidzeklis;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RezervacijaController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'klients_id' => ['required', 'integer', 'exists:klients,klients_id'],
        ]);

        $rezervacijas = Rezervacija::with(['transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona'])
            ->where('klients_id', $data['klients_id'])
            ->orderByDesc('izveides_datums')
            ->get();

        return response()->json($rezervacijas);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'klients_id' => ['required', 'integer', 'exists:klients,klients_id'],
            'transportlidzeklis_id' => ['required', 'integer', 'exists:transportlidzeklis,transportlidzeklis_id'],
            'sakuma_laiks' => ['required', 'date'],
            'beigu_laiks' => ['required', 'date', 'after:sakuma_laiks'],
        ]);

        $transport = Transportlidzeklis::findOrFail($data['transportlidzeklis_id']);

        if ($transport->statuss === 'neaktivs') {
            return response()->json(['message' => 'Transportlīdzeklis nav pieejams.'], 409);
        }

        $start = Carbon::parse($data['sakuma_laiks']);
        $end = Carbon::parse($data['beigu_laiks']);

        $conflict = Rezervacija::where('transportlidzeklis_id', $transport->transportlidzeklis_id)
            ->where(function ($query) use ($start, $end) {
                $query->where('sakuma_laiks', '<', $end)
                      ->where('beigu_laiks', '>', $start);
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'Transportlīdzeklis vairs nav pieejams izvēlētajā laikā.'], 409);
        }

        $days = max(1, $start->diffInDays($end));
        $sum = $days * $transport->dienas_nomas_cena;

        $rezervacija = Rezervacija::create([
            'klients_id' => $data['klients_id'],
            'transportlidzeklis_id' => $transport->transportlidzeklis_id,
            'sakuma_laiks' => $start,
            'beigu_laiks' => $end,
            'rezervacijas_datums' => Carbon::today(),
            'izveides_datums' => Carbon::now(),
            'kopa_summa' => $sum,
            'maksajuma_datums' => null,
            'apmaksas_statuss' => 'neapmaksata',
        ]);

        return response()->json($rezervacija->load(['transportlidzeklis.veids', 'transportlidzeklis.sniedzejs.persona']), 201);
    }

    public function pay(Request $request, $id)
    {
        $data = $request->validate([
            'klients_id' => ['required', 'integer', 'exists:klients,klients_id'],
        ]);

        $rezervacija = Rezervacija::with(['transportlidzeklis'])->findOrFail($id);

        if ($rezervacija->klients_id !== $data['klients_id']) {
            return response()->json(['message' => 'Nav atļauts apmaksāt šo rezervāciju.'], 403);
        }

        if ($rezervacija->apmaksas_statuss === 'apmaksata') {
            return response()->json(['message' => 'Rezervācija jau apmaksāta.'], 409);
        }

        $transaction = 'TX-' . strtoupper(Str::random(12));

        $maksajums = Maksajums::create([
            'rezervacija_id' => $rezervacija->rezervacija_id,
            'summa' => $rezervacija->kopa_summa,
            'statuss' => 'apstiprinats',
            'tranzakcijas_numurs' => $transaction,
            'rekins' => null,
        ]);

        $rezervacija->update([
            'apmaksas_statuss' => 'apmaksata',
            'maksajuma_datums' => Carbon::today(),
        ]);

        return response()->json([
            'rezervacija' => $rezervacija->refresh(),
            'maksajums' => $maksajums,
        ]);
    }
}
