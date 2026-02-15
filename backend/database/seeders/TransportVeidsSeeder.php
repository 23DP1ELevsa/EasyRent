<?php

namespace Database\Seeders;

use App\Models\TransportliedzieklsVeids;
use Illuminate\Database\Seeder;

class TransportVeidsSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Auto',
            'SUV',
            'Kravas auto',
            'Motocikls',
            'Velosipēds',
            'Laiva',
            'Ūdens motocikls',
        ];

        foreach ($types as $type) {
            TransportliedzieklsVeids::firstOrCreate(
                ['nosaukums' => $type],
                ['tips' => $type]
            );
        }
    }
}
