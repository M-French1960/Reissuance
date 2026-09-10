<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avis de comparaison faciale.
 *
 * Table SEPAREE de `verification_steps`, et c'est le point important : l'avis
 * de la machine n'est pas le resultat de l'etape. L'etape 3 porte la decision
 * de l'officier ; cette table porte ce que la machine a dit, avec son score,
 * pour qu'on puisse plus tard reconstituer sur quoi l'officier s'est appuye —
 * et, le cas echeant, etablir qu'il a passe outre.
 *
 * CE QUI N'EST PAS STOCKE : aucun gabarit biometrique, aucun encodage de
 * visage, aucune image. Uniquement l'issue et le score. Les photographies
 * elles-memes vivent dans `request_attachments`, avec leur propre retention.
 * Voir docs/BIOMETRIE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facial_comparisons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->unsignedInteger('cycle')->default(1);
            $table->string('outcome', 20);
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'cycle']);
        });

        DB::statement("ALTER TABLE facial_comparisons ADD CONSTRAINT facial_comparisons_outcome_check
            CHECK (outcome IN ('match','no_match','inconclusive','unavailable'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('facial_comparisons');
    }
};
