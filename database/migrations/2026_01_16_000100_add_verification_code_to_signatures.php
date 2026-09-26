<?php

declare(strict_types=1);

use App\Models\DocumentSignature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le code qui permet de verifier un acte sans compte (D-088).
 *
 * POURQUOI UN CODE, ET PAS LA REFERENCE DU DOSSIER. La reference apparait dans
 * les courriels, dans les notifications, dans l'espace du demandeur : elle
 * circule. Un code de verification ne sert qu'a cela, il est imprime sur
 * l'acte, et il peut etre change sans toucher au dossier.
 *
 * POURQUOI 12 CARACTERES SUR UN ALPHABET DE 32. Cela fait 60 bits d'entropie :
 * meme sans limitation de debit, une recherche exhaustive est hors de portee.
 * La limitation de debit est la, mais elle n'est pas la seule barriere.
 * L'alphabet exclut O, 0, I et 1, qui se confondent quand on recopie a la main
 * un code lu sur un papier — c'est un code destine a etre retape.
 *
 * LES ACTES DEJA SIGNES RECOIVENT UN CODE, MAIS LEUR PDF NE LE PORTE PAS : il
 * a ete genere et stocke au moment de la signature, et rien ne le regenere.
 * Seuls les actes signes a partir de maintenant sont verifiables par une
 * administration qui les recoit. C'est dit dans D-088 plutot que decouvert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table): void {
            $table->string('verification_code', 32)->nullable()->after('document_hash');
        });

        // Les signatures existantes recoivent un code : la colonne devient
        // obligatoire juste apres, et une ligne sans code serait un acte
        // invérifiable pour toujours.
        DocumentSignature::query()->whereNull('verification_code')->each(
            fn (DocumentSignature $signature) => $signature->forceFill([
                'verification_code' => DocumentSignature::generateVerificationCode(),
            ])->save()
        );

        Schema::table('document_signatures', function (Blueprint $table): void {
            $table->string('verification_code', 32)->nullable(false)->change();
            $table->unique('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table): void {
            $table->dropUnique(['verification_code']);
            $table->dropColumn('verification_code');
        });
    }
};
