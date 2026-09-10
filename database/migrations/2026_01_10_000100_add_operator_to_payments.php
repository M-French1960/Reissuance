<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operateur de paiement choisi par le demandeur.
 *
 * Les deux specialisations de « Make Payment » au diagramme : Orange Money et
 * Mobile Money. Le choix est conserve avec l'encaissement, parce qu'un
 * rapprochement comptable se fait par operateur.
 *
 * Colonne NULLABLE : les encaissements ouverts avant cette migration n'ont pas
 * d'operateur, et leur en inventer un serait ecrire une donnee fausse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('operator', 30)->nullable()->after('provider');
            $table->index(['operator', 'status']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_operator_check
            CHECK (operator IS NULL OR operator IN ('orange_money','mtn_mobile_money'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_operator_check');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['operator', 'status']);
            $table->dropColumn('operator');
        });
    }
};
