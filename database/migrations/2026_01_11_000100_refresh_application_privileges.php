<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rejoue les droits du compte applicatif, une fois toutes les tables creees.
 *
 * MySQL n'a pas d'equivalent d'ALTER DEFAULT PRIVILEGES : une table creee
 * apres la migration des droits n'en recoit AUCUN. Cette migration doit donc
 * rester la DERNIERE, et toute migration ajoutant une table doit etre suivie
 * d'un `php artisan phoenix:droits`.
 *
 * Ce n'est pas une convention qu'on espere respectee : un test verifie qu'une
 * table sans droits n'existe pas (PrivilegesTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        ApplicationPrivileges::apply(DB::getDefaultConnection());
    }

    public function down(): void {}
};
