<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportlidzeklis', function (Blueprint $table) {
            $table->id('transportlidzeklis_id');
            $table->foreignId('sniedzejs_id')
                ->constrained('pakalpojumu_sniedzejs', 'sniedzejs_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('veids_id')
                ->constrained('transportlidzekla_veids', 'veids_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('marka', 50);
            $table->string('modelis', 50);
            $table->string('atrumkarba', 20)->nullable();
            $table->string('degvielas_veids', 20)->nullable();
            $table->decimal('dienas_nomas_cena', 10, 2);
            $table->string('adrese', 255);
            $table->enum('statuss', ['pieejams', 'aiznemts', 'neaktivs']);
            $table->string('registracijas_numurs', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportlidzeklis');
    }
};