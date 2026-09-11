<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projet d'acte redige par l'officier (D-064, lecture 2 du diagramme).
 *
 * CE QUE CETTE TABLE N'EST PAS. Ce n'est pas `document_signatures`, et les
 * deux ne doivent jamais se confondre : un projet n'est pas un acte. Il n'a ni
 * signataire, ni preuve de signature, ni valeur. Le citoyen n'y a aucun acces.
 *
 * POURQUOI UNE EMPREINTE DE CONTENU, ET NON DU PDF. Le projet porte un bandeau
 * « PROJET » que l'acte final n'a pas : les deux fichiers different donc
 * forcement, et comparer leurs octets ne dirait rien. Ce qu'il faut verifier
 * est que le CONTENU — les champs de la demande qui composent l'acte — n'a pas
 * bouge entre la redaction par l'officier et la signature du maire.
 *
 * Sans cette verification, deplacer la redaction vers l'officier ouvrirait
 * exactement la faille que le brief 4.3 interdit : l'officier pourrait
 * modifier le dossier apres que le maire a lu le projet, et le maire signerait
 * autre chose que ce qu'il a vu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('act_drafts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')
                ->constrained('reissuance_requests')
                ->cascadeOnDelete();

            // L'officier qui a redige : la responsabilite du contenu est
            // desormais la sienne, elle doit etre nominative.
            $table->foreignId('officer_id')->constrained('users');

            // Empreinte des champs qui composent l'acte, au moment de la
            // redaction. Relue et comparee a la signature.
            $table->string('content_hash', 64);

            // Le PDF du projet, hors de public/, sous un chemin opaque.
            $table->string('document_path');

            $table->timestamps();

            $table->index(['request_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE `act_drafts` ADD CONSTRAINT `act_drafts_hash_format_check`
             CHECK (`content_hash` REGEXP '^[0-9a-f]{64}$')"
        );

        // Le lien de l'acte signe vers le projet dont il est issu : sans lui,
        // on ne saurait pas QUI a redige ce qui a ete signe.
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->foreignId('draft_id')->nullable()->after('mayor_id')
                ->constrained('act_drafts')->nullOnDelete();
        });

        ApplicationPrivileges::apply();
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_id');
        });

        Schema::dropIfExists('act_drafts');
    }
};
