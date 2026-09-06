<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referentiel geographique unique.
 *
 * L'audit du prototype a releve trois listes de centres incoherentes entre
 * form.html et officer-dashboard.html, rendant le filtre par centre inoperant
 * (docs/AUDIT_FRONTEND.md 4.1). Une seule source, avec cles etrangeres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('region');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('civil_status_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('city');
            // Obligatoire : c'est ce rattachement qui designe le maire competent
            // pour une demande (docs/DATA_MODEL.md 2.1).
            $table->foreignId('commune_id')->constrained('communes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['commune_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_status_centers');
        Schema::dropIfExists('communes');
    }
};
