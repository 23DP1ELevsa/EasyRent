<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rezervacija', function (Blueprint $table) {
            $table->id('rezervacija_id');
            $table->foreignId('klients_id')
                ->constrained('klients', 'klients_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('transportlidzeklis_id')
                ->constrained('transportlidzeklis', 'transportlidzeklis_id')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->dateTime('sakuma_laiks');
            $table->dateTime('beigu_laiks');
            $table->date('rezervacijas_datums');
            $table->dateTime('izveides_datums')->useCurrent();
            $table->decimal('kopa_summa', 10, 2);
            $table->date('maksajuma_datums')->nullable();
            $table->enum('apmaksas_statuss', ['neapmaksata', 'apmaksata']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rezervacija');
    }
};