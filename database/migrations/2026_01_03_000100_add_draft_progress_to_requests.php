<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reprise d'un brouillon interrompu.
 *
 * D-010 ayant ecarte Livewire, le formulaire multi-etapes persiste chaque
 * etape validee cote serveur : POST -> enregistrement -> redirection -> GET.
 * Cette colonne dit ou reprendre. Plus robuste sur reseau instable qu'une
 * sauvegarde continue, et sans perte en cas de coupure entre deux etapes
 * (8.1 du brief).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reissuance_requests', function (Blueprint $table): void {
            $table->unsignedTinyInteger('last_completed_step')->default(0);
        });

        DB::statement('ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_step_check
            CHECK (last_completed_step BETWEEN 0 AND 4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT IF EXISTS reissuance_requests_step_check');

        Schema::table('reissuance_requests', function (Blueprint $table): void {
            $table->dropColumn('last_completed_step');
        });
    }
};
