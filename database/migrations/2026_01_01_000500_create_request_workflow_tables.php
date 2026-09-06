<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracabilite du travail : pieces, etapes de verification, decisions, signature.
 *
 * Ces entites ne figurent pas au 6 du brief. Elles decoulent des parcours
 * decrits au 5 et sont indispensables pour qu'une verification interrompue
 * soit reprenable et qu'on sache qui a fait quoi, quand, avec quel resultat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('disk', 32)->default('private');
            // Identifiant opaque, jamais derive du nom ni du numero de piece.
            $table->string('path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->timestamp('captured_at')->nullable();
            // Politique de retention : aucune duree par defaut n'est codee tant
            // que la question B3 de COMPLIANCE_OPEN_QUESTIONS.md est ouverte.
            $table->timestamp('purge_after')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'kind']);
            $table->index('purge_after');
        });

        DB::statement("ALTER TABLE request_attachments ADD CONSTRAINT request_attachments_kind_check
            CHECK (kind IN ('selfie','id_document'))");

        Schema::create('verification_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            // Incremente a chaque retour du maire : une reprise n'ecrase jamais
            // ce qui avait ete constate au passage precedent.
            $table->unsignedSmallInteger('cycle')->default(1);
            $table->unsignedTinyInteger('step');
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->string('result', 32)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'cycle', 'step']);
        });

        DB::statement('ALTER TABLE verification_steps ADD CONSTRAINT verification_steps_step_check
            CHECK (step BETWEEN 1 AND 5)');

        // provider_unavailable est un resultat de premier rang : le 9 du brief
        // exige qu'une panne externe ne bloque pas l'officier sans explication.
        DB::statement("ALTER TABLE verification_steps ADD CONSTRAINT verification_steps_result_check
            CHECK (result IS NULL OR result IN ('match','no_match','inconclusive','provider_unavailable'))");

        Schema::create('request_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 16);
            $table->string('decision', 32);
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'created_at']);
        });

        DB::statement("ALTER TABLE request_decisions ADD CONSTRAINT request_decisions_decision_check
            CHECK (decision IN ('accepted','rejected','escalated','signed','approved_by_exception','returned'))");

        // Motif obligatoire pour tout ce qui n'est pas une acceptation ou une
        // signature simple (docs/STATE_MACHINE.md 2, colonne Motif).
        DB::statement("ALTER TABLE request_decisions ADD CONSTRAINT request_decisions_reason_required_check CHECK (
            decision IN ('accepted','signed')
            OR (reason IS NOT NULL AND length(btrim(reason)) >= 10)
        )");

        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('reissuance_requests')->restrictOnDelete();
            $table->foreignId('mayor_id')->constrained('users')->restrictOnDelete();
            // Empreinte du PDF produit : permet de prouver plus tard qu'un acte
            // presente est bien celui qui a ete signe.
            $table->string('document_hash', 64);
            $table->string('provider', 64);
            $table->jsonb('signature_payload')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        Schema::create('notifications_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('reissuance_requests')->cascadeOnDelete();
            $table->string('channel', 32)->default('mail');
            $table->string('type', 64);
            $table->jsonb('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['sent_at', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_outbox');
        Schema::dropIfExists('document_signatures');
        Schema::dropIfExists('request_decisions');
        Schema::dropIfExists('verification_steps');
        Schema::dropIfExists('request_attachments');
    }
};
