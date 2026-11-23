<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rezervaciju_rekinu_arhivs', function (Blueprint $table) {
            $table->foreignId('maksajums_id')->primary()
                ->constrained('maksajums', 'maksajums_id')->restrictOnDelete();
            $table->longText('saturs');
        });
    }

    public function down(): void
    {
        if (DB::table('rezervaciju_rekinu_arhivs')->exists()) {
            throw new RuntimeException('The invoice archive contains financial records.');
        }

        Schema::dropIfExists('rezervaciju_rekinu_arhivs');
    }
};
