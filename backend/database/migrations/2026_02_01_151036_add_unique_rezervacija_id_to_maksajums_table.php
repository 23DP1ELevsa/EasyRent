<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DELETE FROM maksajums
            WHERE maksajums_id NOT IN (
                SELECT maksajums_id FROM (
                    SELECT MAX(maksajums_id) AS maksajums_id
                    FROM maksajums
                    GROUP BY rezervacija_id
                ) AS latest_maksajumi
            )
        ");

        Schema::table('maksajums', function (Blueprint $table) {
            $table->unique('rezervacija_id', 'uq_maksajums_rezervacija_id');
        });
    }

    public function down(): void
    {
        Schema::table('maksajums', function (Blueprint $table) {
            $table->dropUnique('uq_maksajums_rezervacija_id');
        });
    }
};
