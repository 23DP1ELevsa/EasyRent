<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportlidzekla_foto', function (Blueprint $table) {
            $table->id('foto_id');
            $table->foreignId('transportlidzeklis_id')->constrained('transportlidzeklis', 'transportlidzeklis_id')->cascadeOnDelete();
            $table->string('mime_type', 32);
            $table->mediumText('attels');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportlidzekla_foto');
    }
};
