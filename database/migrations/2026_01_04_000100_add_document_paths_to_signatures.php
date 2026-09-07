<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emplacement de l'acte signé et de sa preuve.
 *
 * Comme les pièces d'identité, ils vivent hors de public/ et ne sont servis
 * que par un contrôleur qui vérifie la Policy et journalise (D-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table): void {
            $table->string('document_path')->nullable()->after('document_hash');
            $table->string('proof_path')->nullable()->after('document_path');
            $table->boolean('legally_binding')->default(false)->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table): void {
            $table->dropColumn(['document_path', 'proof_path', 'legally_binding']);
        });
    }
};
