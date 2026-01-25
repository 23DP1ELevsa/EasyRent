<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atsauksme', function (Blueprint $table) {
            $table->id('atsauksme_id');
            $table->foreignId('klients_id')
                ->constrained('klients', 'klients_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('transportlidzeklis_id')
                ->constrained('transportlidzeklis', 'transportlidzeklis_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->integer('vertejums');
            $table->text('komentars')->nullable();
            $table->date('datums');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atsauksme');
    }
};