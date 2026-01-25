<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maksajums', function (Blueprint $table) {
            $table->id('maksajums_id');
            $table->foreignId('rezervacija_id')
                ->constrained('rezervacija', 'rezervacija_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->decimal('summa', 10, 2);
            $table->enum('statuss', ['gaida', 'apstiprinats', 'atteikts']);
            $table->string('tranzakcijas_numurs', 100)->unique();
            $table->string('rekins', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maksajums');
    }
};