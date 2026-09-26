<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Une piece manquante, demandee apres l'envoi » (D-087).
 *
 * LA LIMITE QUE CELA LEVE. Une fois la demande envoyee, le demandeur ne
 * pouvait plus toucher a ses photos : la Policy `update` exige l'etat
 * brouillon. Une photo floue condamnait donc le dossier au rejet, alors que la
 * personne etait de bonne foi et que le probleme tenait a un reflet.
 *
 * POURQUOI UNE TABLE, ET NON UN CHAMP SUR LA DEMANDE. Un officier peut avoir a
 * demander deux fois. L'historique de ce qui a ete demande, par qui, quand, et
 * ce qui y a repondu, fait partie de la trace du dossier — au meme titre que
 * les decisions et les etapes de verification.
 *
 * POURQUOI LE DEMANDEUR NE PEUT PAS EN OUVRIR UN LUI-MEME. Remplacer une piece
 * d'identite est une operation sensible : sans demande prealable d'un officier,
 * n'importe qui pourrait remplacer la piece deja verifiee par une autre, apres
 * coup. C'est l'officier qui ouvre, le demandeur qui repond.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_complements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 32);
            $table->text('message');
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('fulfilled_attachment_id')->nullable()
                ->constrained('request_attachments')->nullOnDelete();
            $table->timestamps();

            $table->index(['request_id', 'fulfilled_at']);
        });

        // La piece demandee est l'une de celles que le dossier porte deja. Une
        // valeur libre ouvrirait un depot de n'importe quel document.
        DB::statement("ALTER TABLE request_complements ADD CONSTRAINT request_complements_kind_check
            CHECK (kind IN ('id_document','selfie'))");

        // Un motif vide n'explique rien : le demandeur doit savoir CE QUI ne va
        // pas, sinon il renvoie la meme photo.
        DB::statement('ALTER TABLE request_complements ADD CONSTRAINT request_complements_message_check
            CHECK (CHAR_LENGTH(TRIM(message)) >= 10)');

        /*
         * UNE SEULE DEMANDE OUVERTE A LA FOIS, IMPOSEE PAR LA BASE.
         *
         * MySQL n'a pas d'index unique partiel. Une colonne generee qui vaut
         * l'identifiant de la demande tant que le complement est ouvert, et
         * NULL une fois satisfait, donne le meme resultat : deux lignes
         * ouvertes pour la meme demande se heurtent a l'unicite, et les lignes
         * satisfaites valent NULL, qui ne s'oppose a rien.
         *
         * Sans cela, deux demandes ouvertes rendraient « quelle piece
         * attend-on ? » ambigue, et l'ecran du demandeur n'aurait pas de
         * reponse.
         */
        DB::statement('ALTER TABLE request_complements
            ADD COLUMN open_request_id BIGINT UNSIGNED
            GENERATED ALWAYS AS (CASE WHEN fulfilled_at IS NULL THEN request_id END) STORED');

        DB::statement('ALTER TABLE request_complements
            ADD CONSTRAINT request_complements_one_open UNIQUE (open_request_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('request_complements');
    }
};
