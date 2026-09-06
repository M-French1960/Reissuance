<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit en ajout seul (4.4 du brief).
 *
 * L'inalterabilite est appliquee par revocation de droits sur le role
 * applicatif, et non par convention : une Policy se contourne par un bug,
 * une revocation de droit non. La revocation elle-meme est posee par la
 * migration 2026_01_01_000800.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable : un echec de connexion n'a pas d'acteur connu.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 16)->nullable();
            $table->string('action', 64);
            $table->string('auditable_type', 64)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('session_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
