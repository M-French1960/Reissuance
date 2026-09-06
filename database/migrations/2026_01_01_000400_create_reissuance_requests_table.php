<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reissuance_requests', function (Blueprint $table) {
            $table->id();
            // Reference publique non sequentielle : un compteur laisserait
            // deduire le volume traite et enumerer les demandes.
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Figes a l'envoi, jamais recalcules : le centre et la commune
            // determinent qui est competent, y compris si le referentiel change.
            $table->foreignId('civil_status_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('status', 32)->default('draft');
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('verification_cycle')->default(1);

            $table->string('document_type', 32)->default('birth_certificate');
            $table->string('reason', 16)->nullable();
            $table->unsignedSmallInteger('copies_requested')->default(1);

            // Champs de l'acte, repris de form.html : ce sont eux qui permettent
            // de retrouver l'acte d'origine (docs/AUDIT_FRONTEND.md 7.3).
            $table->string('full_name_at_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->unsignedSmallInteger('registration_year')->nullable();
            $table->string('original_certificate_number')->nullable();

            $table->string('father_name')->nullable();
            $table->string('father_nationality')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_nationality')->nullable();
            $table->string('parents_address')->nullable();

            $table->timestamp('consent_given_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Chainage des reprises : signed et rejected sont terminaux, toute
            // reprise cree une nouvelle demande liee (docs/STATE_MACHINE.md 2).
            $table->foreignId('supersedes_id')->nullable()->constrained('reissuance_requests')->nullOnDelete();

            $table->timestamps();

            $table->index(['civil_status_center_id', 'status', 'submitted_at']);
            $table->index(['commune_id', 'status', 'submitted_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['assigned_officer_id', 'status']);
        });

        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_status_check
            CHECK (status IN ('draft','pending','under_review','awaiting_signature','escalated','signed','rejected'))");

        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_reason_check
            CHECK (reason IS NULL OR reason IN ('lost','damaged'))");

        DB::statement('ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_copies_check
            CHECK (copies_requested >= 1 AND copies_requested <= 10)');

        // Une demande envoyee a forcement un centre et une commune.
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_submitted_scope_check CHECK (
            status = 'draft'
            OR (civil_status_center_id IS NOT NULL AND commune_id IS NOT NULL AND submitted_at IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('reissuance_requests');
    }
};
