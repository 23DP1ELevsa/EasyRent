<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportlidzeklis', function (Blueprint $table) {
            $table->string('vaditaja_apliecibas_kategorija', 5)->nullable()->index();
        });

        Schema::table('transportlidzekla_foto', function (Blueprint $table) {
            $table->unsignedTinyInteger('position')->default(1);
            $table->char('content_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transportlidzekla_foto', function (Blueprint $table) {
            $table->dropColumn(['position', 'content_hash']);
        });

        Schema::table('transportlidzeklis', function (Blueprint $table) {
            $table->dropIndex(['vaditaja_apliecibas_kategorija']);
            $table->dropColumn('vaditaja_apliecibas_kategorija');
        });
    }
};
