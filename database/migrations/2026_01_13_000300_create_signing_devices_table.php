<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appareils de signature enroles par les officiels — WebAuthn (D-070).
 *
 * CE QUE CETTE TABLE NE CONTIENT PAS, et c'est l'essentiel : aucune donnee
 * biometrique. Pas d'image, pas de gabarit, pas de vecteur, pas d'empreinte de
 * visage. Le visage — ou le doigt — ne quitte jamais l'appareil du maire : il
 * y deverrouille localement une cle privee qui, elle non plus, ne sort jamais.
 * Le serveur ne connait que la cle PUBLIQUE correspondante.
 *
 * C'est la difference de fond avec la comparaison faciale du demandeur
 * (docs/BIOMETRIE.md), qui envoie des photographies a un service. Ici, rien
 * n'est envoye et rien n'est conserve.
 *
 * `sign_count` est le compteur d'usage rendu par l'appareil. Un compteur qui
 * recule ou stagne peut signaler une cle clonee ; on le conserve pour pouvoir
 * le constater.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * L'identifiant de la cle, tel que l'appareil le rend. Unique sur
             * toute la table et non par utilisateur : un meme authentificateur
             * ne doit pas pouvoir etre enrole sur deux comptes officiels.
             */
            $table->string('credential_id', 512)->unique();

            // La cle publique, serialisee par la bibliotheque WebAuthn.
            $table->text('public_key');

            /*
             * Le nom que l'officiel donne a son appareil. Il en aura
             * plusieurs — un telephone, un ordinateur — et devra pouvoir
             * revoquer le bon en cas de perte.
             */
            $table->string('label', 80);

            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('aaguid', 64)->nullable();
            $table->json('transports')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_devices');
    }
};
