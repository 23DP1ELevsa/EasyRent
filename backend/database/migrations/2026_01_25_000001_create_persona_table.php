<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona', function (Blueprint $table) {
            $table->id('persona_id');
            $table->string('vards', 50);
            $table->string('uzvards', 50);
            $table->string('epasts', 100)->unique();
            $table->string('parole', 255);
            $table->string('kontakttalrunis', 20)->nullable();
            $table->enum('loma', ['klients', 'pakalpojumu_sniedzejs', 'administrators']);
            $table->string('bankas_konts', 34)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};