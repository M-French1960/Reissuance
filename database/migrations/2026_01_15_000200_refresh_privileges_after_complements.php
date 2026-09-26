<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rejoue les droits apres la creation de `request_complements`.
 *
 * MySQL n'accorde aucun droit sur une table creee apres la migration des
 * droits. La regle du projet : toute migration qui cree une table est suivie
 * d'un rejeu, et CELUI-CI doit rester le dernier.
 */
return new class extends Migration
{
    public function up(): void
    {
        ApplicationPrivileges::apply(DB::getDefaultConnection());
    }

    public function down(): void {}
};
