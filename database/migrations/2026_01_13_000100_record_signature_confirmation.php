<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Par quel moyen le maire a confirme son identite au moment de signer.
 *
 * POURQUOI UNE COLONNE plutot qu'une cle du JSON de preuve : c'est une donnee
 * qu'un controle voudra interroger — « combien d'actes ont ete signes avec un
 * code de secours plutot qu'avec l'application d'authentification ? ». Une
 * telle question ne doit pas exiger de parcourir du JSON ligne a ligne.
 *
 * Nullable : les actes delivres avant D-069 n'ont pas ete confirmes. Les
 * marquer retroactivement d'une methode serait ecrire une histoire fausse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->string('confirmation_method', 32)->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn('confirmation_method');
        });
    }
};
