<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transportlidzekla_veids', 'tips')) {
            Schema::table('transportlidzekla_veids', function (Blueprint $table) {
                $table->string('tips', 50)->after('nosaukums');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transportlidzekla_veids', 'tips')) {
            Schema::table('transportlidzekla_veids', function (Blueprint $table) {
                $table->dropColumn('tips');
            });
        }
    }
};
