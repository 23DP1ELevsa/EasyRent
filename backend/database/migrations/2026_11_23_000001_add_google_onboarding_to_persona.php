<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->string('vards', 50)->nullable()->change();
            $table->string('uzvards', 50)->nullable()->change();
            $table->string('epasts', 255)->nullable()->change();
            $table->string('parole')->nullable()->change();
            $googleId = $table->string('google_id')->nullable()->unique();
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $googleId->charset('ascii')->collation('ascii_bin');
            }
            $table->boolean('profile_completed')->default(true);
        });

        Schema::table('klients', function (Blueprint $table) {
            $table->string('lietotajvards', 30)->nullable()->change();
        });

        Schema::table('pakalpojumu_sniedzejs', function (Blueprint $table) {
            $table->string('registracijas_numurs', 20)->nullable()->change();
            $table->string('iela', 150)->nullable()->change();
            $table->string('majas_numurs', 20)->nullable()->change();
            $table->string('pilseta', 100)->nullable()->change();
            $table->string('pasta_indekss', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('persona')->whereNotNull('google_id')->exists()
            || DB::table('persona')->whereRaw('length(epasts) > 100')->exists()) {
            throw new RuntimeException('The previous schema cannot represent existing accounts.');
        }

        Schema::table('pakalpojumu_sniedzejs', function (Blueprint $table) {
            $table->string('registracijas_numurs', 20)->change();
            $table->string('iela', 150)->default('')->change();
            $table->string('majas_numurs', 20)->default('')->change();
            $table->string('pilseta', 100)->default('')->change();
            $table->string('pasta_indekss', 20)->default('')->change();
        });

        Schema::table('klients', function (Blueprint $table) {
            $table->string('lietotajvards', 30)->change();
        });

        Schema::table('persona', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'profile_completed']);
            $table->string('vards', 50)->change();
            $table->string('uzvards', 50)->change();
            $table->string('epasts', 100)->change();
            $table->string('parole')->change();
        });
    }
};
