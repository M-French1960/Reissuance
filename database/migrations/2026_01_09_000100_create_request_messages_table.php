<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Contact Officer » du diagramme de cas d'utilisation.
 *
 * Un fil de messages ATTACHE A UNE DEMANDE, pas une messagerie generale : on
 * n'ecrit pas a un agent, on ecrit AU SUJET d'un dossier. C'est ce qui permet
 * a n'importe quel officier du centre de reprendre la conversation si celui
 * qui suivait le dossier est absent, et c'est ce qui rattache chaque echange
 * a une trace verifiable.
 *
 * Ce que la table ne fait PAS : porter des pieces jointes. Un canal de
 * messages qui accepte des fichiers devient une seconde voie de depot de
 * pieces d'identite, hors du controle de IdentityDocumentStore. Si le
 * demandeur doit fournir une piece, cela passe par le dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('author_role', 16);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'created_at']);
        });

        // Seuls le demandeur et les agents en charge du dossier ecrivent ici.
        // L'administrateur en est exclu : le 4.2 lui interdit le contenu des
        // dossiers, et un fil de messages EST du contenu de dossier.
        DB::statement("ALTER TABLE request_messages ADD CONSTRAINT request_messages_author_role_check
            CHECK (author_role IN ('citizen','officer','mayor'))");

        // Un message vide n'est pas un message.
        DB::statement('ALTER TABLE request_messages ADD CONSTRAINT request_messages_body_check
            CHECK (CHAR_LENGTH(TRIM(body)) >= 2)');
    }

    public function down(): void
    {
        Schema::dropIfExists('request_messages');
    }
};
