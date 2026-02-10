<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pakalpojumu_sniedzejs', function (Blueprint $table) {
            $table->string('iela', 150)->default('')->after('registracijas_numurs');
            $table->string('majas_numurs', 20)->default('')->after('iela');
            $table->string('dzivokla_numurs', 20)->nullable()->after('majas_numurs');
            $table->string('pilseta', 100)->default('')->after('dzivokla_numurs');
            $table->string('pasta_indekss', 20)->default('')->after('pilseta');
            $table->dropColumn('atrasanas_adrese');
        });
    }

    public function down(): void
    {
        Schema::table('pakalpojumu_sniedzejs', function (Blueprint $table) {
            $table->string('atrasanas_adrese', 255)->after('registracijas_numurs');
            $table->dropColumn(['iela', 'majas_numurs', 'dzivokla_numurs', 'pilseta', 'pasta_indekss']);
        });
    }
};
