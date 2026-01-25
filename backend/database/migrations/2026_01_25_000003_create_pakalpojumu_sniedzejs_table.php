<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pakalpojumu_sniedzejs', function (Blueprint $table) {
            $table->id('sniedzejs_id');
            $table->foreignId('persona_id')->unique()
                ->constrained('persona', 'persona_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('registracijas_numurs', 20);
            $table->string('atrasanas_adrese', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pakalpojumu_sniedzejs');
    }
};