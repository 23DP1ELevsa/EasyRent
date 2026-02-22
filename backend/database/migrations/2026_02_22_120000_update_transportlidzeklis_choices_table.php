<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE transportlidzeklis SET atrumkarba = CASE
            WHEN atrumkarba IS NULL OR TRIM(atrumkarba) = '' THEN '-'
            WHEN LOWER(atrumkarba) LIKE 'auto%' OR LOWER(atrumkarba) LIKE '%automat%' THEN 'Automāts'
            WHEN LOWER(atrumkarba) LIKE 'meh%' OR LOWER(atrumkarba) LIKE '%manu%' THEN 'Mehānika'
            ELSE '-'
        END");

        DB::statement("UPDATE transportlidzeklis SET degvielas_veids = CASE
            WHEN degvielas_veids IS NULL OR TRIM(degvielas_veids) = '' THEN '-'
            WHEN LOWER(degvielas_veids) LIKE 'benz%' THEN 'Benzīns'
            WHEN LOWER(degvielas_veids) LIKE 'dizel%' OR LOWER(degvielas_veids) LIKE 'diesel%' THEN 'Dīzelis'
            WHEN LOWER(degvielas_veids) LIKE 'elektro%' OR LOWER(degvielas_veids) LIKE 'electric%' OR LOWER(degvielas_veids) LIKE 'ev%' THEN 'Elektro'
            ELSE '-'
        END");

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transportlidzeklis MODIFY COLUMN atrumkarba ENUM('Automāts','Mehānika','-') NOT NULL DEFAULT '-'");
            DB::statement("ALTER TABLE transportlidzeklis MODIFY COLUMN degvielas_veids ENUM('Benzīns','Dīzelis','Elektro','-') NOT NULL DEFAULT '-'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transportlidzeklis MODIFY COLUMN atrumkarba VARCHAR(20) NULL");
            DB::statement("ALTER TABLE transportlidzeklis MODIFY COLUMN degvielas_veids VARCHAR(20) NULL");
        }
    }
};
