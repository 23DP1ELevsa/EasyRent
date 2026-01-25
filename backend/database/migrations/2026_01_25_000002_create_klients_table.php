<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klients', function (Blueprint $table) {
            $table->id('klients_id');
            $table->foreignId('persona_id')->unique()
                ->constrained('persona', 'persona_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('lietotajvards', 30)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klients');
    }
};