<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Console\Command;

/**
 * Reapplique les droits du compte applicatif.
 *
 * A lancer apres toute migration qui cree une table : MySQL n'accorde aucun
 * droit sur une table nouvelle. Doit tourner sous le compte PROPRIETAIRE.
 */
class ApplyPrivileges extends Command
{
    protected $signature = 'phoenix:droits {--connection=mysql_owner}';

    protected $description = 'Reapplique les droits du compte applicatif, table par table';

    public function handle(): int
    {
        $n = ApplicationPrivileges::apply((string) $this->option('connection'));

        $this->info("Droits appliqués sur {$n} table(s).");
        $this->line('  Ajout seul  : '.implode(', ', ApplicationPrivileges::APPEND_ONLY));
        $this->line('  Lecture seule : '.implode(', ', ApplicationPrivileges::READ_ONLY));

        return self::SUCCESS;
    }
}
