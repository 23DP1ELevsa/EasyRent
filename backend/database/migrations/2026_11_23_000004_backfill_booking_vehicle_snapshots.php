<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rezervacija')
            ->join('transportlidzeklis', 'transportlidzeklis.transportlidzeklis_id', '=', 'rezervacija.transportlidzeklis_id')
            ->join('transportlidzekla_veids', 'transportlidzekla_veids.veids_id', '=', 'transportlidzeklis.veids_id')
            ->whereNull('rezervacija.transporta_snapshot')
            ->select('rezervacija.rezervacija_id', 'marka', 'modelis', 'atrumkarba', 'degvielas_veids', 'nosaukums')
            ->chunkById(500, function ($rows) {
                DB::transaction(function () use ($rows) {
                    foreach ($rows as $row) {
                        DB::table('rezervacija')->where('rezervacija_id', $row->rezervacija_id)
                            ->whereNull('transporta_snapshot')->update([
                                'transporta_snapshot' => json_encode([
                                    'marka' => $row->marka,
                                    'modelis' => $row->modelis,
                                    'atrumkarba' => $row->atrumkarba,
                                    'degvielas_veids' => $row->degvielas_veids,
                                    'veids' => $row->nosaukums,
                                ], JSON_THROW_ON_ERROR),
                            ]);
                    }
                }, 3);
            }, 'rezervacija.rezervacija_id', 'rezervacija_id');
    }

    public function down(): void
    {
        // Keep historical snapshots until the schema migration is rolled back.
    }
};
