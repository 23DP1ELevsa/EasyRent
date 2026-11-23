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
            $table->softDeletes();
        });

        Schema::table('transportlidzeklis', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->string('registracijas_numurs', 20)->nullable()->change();
        });

        Schema::table('rezervacija', function (Blueprint $table) {
            $table->json('transporta_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('persona')->whereNotNull('deleted_at')->exists()
            || DB::table('transportlidzeklis')->whereNotNull('archived_at')->exists()) {
            throw new RuntimeException('The previous schema cannot represent deleted accounts or archived vehicles.');
        }

        Schema::table('rezervacija', fn (Blueprint $table) => $table->dropColumn('transporta_snapshot'));
        Schema::table('transportlidzeklis', function (Blueprint $table) {
            $table->dropColumn('archived_at');
            $table->string('registracijas_numurs', 20)->change();
        });
        Schema::table('persona', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
