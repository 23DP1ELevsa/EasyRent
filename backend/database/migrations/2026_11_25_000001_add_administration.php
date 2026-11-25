<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->unsignedInteger('session_generation')->default(0);
            $table->string('preferred_locale', 2)->nullable();
            $table->index(['loma', 'persona_id'], 'persona_role_id');
        });
        Schema::table('transportlidzekla_veids', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
        });
        foreach (['transportlidzeklis', 'atsauksme'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('moderation_hidden_at')->nullable()->index();
                $table->text('moderation_reason')->nullable();
            });
        }
        Schema::create('lietotaja_ierobezojums', function (Blueprint $table) {
            $table->id('ierobezojums_id');
            $table->foreignId('persona_id')->constrained('persona', 'persona_id');
            $table->foreignId('administrators_id')->constrained('persona', 'persona_id');
            $table->string('veids', 32);
            $table->string('iemesla_kods', 32)->nullable();
            $table->text('iemesls');
            $table->dateTime('sakuma_laiks');
            $table->dateTime('beigu_laiks')->nullable();
            $table->dateTime('atcelts_at')->nullable();
            $table->foreignId('atcela_persona_id')->nullable()->constrained('persona', 'persona_id');
            $table->text('atcelsanas_iemesls')->nullable();
            $table->timestamps();
            $table->index(['persona_id', 'veids', 'atcelts_at', 'beigu_laiks'], 'restriction_account_active');
        });
        Schema::create('parkapuma_zinojums', function (Blueprint $table) {
            $table->id('zinojums_id');
            $table->foreignId('persona_id')->constrained('persona', 'persona_id');
            $table->string('objekta_veids', 24);
            $table->unsignedBigInteger('objekta_id');
            $table->string('iemesls', 32);
            $table->text('apraksts');
            $table->string('statuss', 16)->default('jauns')->index();
            $table->foreignId('administrators_id')->nullable()->constrained('persona', 'persona_id');
            $table->text('lemuma_komentars')->nullable();
            $table->dateTime('izskatits_at')->nullable();
            $table->timestamps();
            $table->unique(['persona_id', 'objekta_veids', 'objekta_id', 'iemesls'], 'report_unique_reason');
            $table->index(['objekta_veids', 'objekta_id'], 'report_target');
        });
        Schema::create('administratora_darbiba', function (Blueprint $table) {
            $table->id('darbiba_id');
            $table->foreignId('administrators_id')->constrained('persona', 'persona_id');
            $table->string('darbiba', 40)->index();
            $table->string('objekta_veids', 24);
            $table->unsignedBigInteger('objekta_id');
            $table->text('iemesls')->nullable();
            $table->json('iepriekseja_vertiba')->nullable();
            $table->json('jauna_vertiba')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['objekta_veids', 'objekta_id'], 'audit_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administratora_darbiba');
        Schema::dropIfExists('parkapuma_zinojums');
        Schema::dropIfExists('lietotaja_ierobezojums');
        foreach (['atsauksme', 'transportlidzeklis'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_moderation_hidden_at_index');
                $table->dropColumn(['moderation_hidden_at', 'moderation_reason']);
            });
        }
        Schema::table('transportlidzekla_veids', fn (Blueprint $table) => $table->dropColumn('deactivated_at'));
        Schema::table('persona', function (Blueprint $table) {
            $table->dropIndex('persona_role_id');
            $table->dropColumn(['session_generation', 'preferred_locale']);
        });
    }
};
