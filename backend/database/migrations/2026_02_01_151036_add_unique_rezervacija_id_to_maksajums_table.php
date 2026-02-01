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
            DELETE m1 FROM maksajums m1
            INNER JOIN maksajums m2
              ON m1.rezervacija_id = m2.rezervacija_id
             AND m1.maksajums_id < m2.maksajums_id
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
