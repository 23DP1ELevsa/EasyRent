<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persona', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });

        // Preserve access for every account present when verification is introduced.
        DB::table('persona')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->foreignId('persona_id')->primary()->constrained('persona', 'persona_id')->cascadeOnDelete();
            $table->string('email', 255);
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
        Schema::table('persona', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
