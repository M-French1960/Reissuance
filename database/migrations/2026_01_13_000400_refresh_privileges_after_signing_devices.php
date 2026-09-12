<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rejoue les droits apres la creation de `signing_devices`.
 *
 * MySQL n'accorde aucun droit sur une table creee apres la migration des
 * droits, et n'a pas d'equivalent d'ALTER DEFAULT PRIVILEGES. La regle du
 * projet est donc : toute migration qui cree une table est suivie d'un
 * rejeu des droits, et CELUI-CI doit rester le dernier.
 *
 * Ce n'est pas theorique — les tests de la signature par appareil sont tombes
 * sur « SELECT command denied » avant que cette migration n'existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        ApplicationPrivileges::apply(DB::getDefaultConnection());
    }

    public function down(): void {}
};
