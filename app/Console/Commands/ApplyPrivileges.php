<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Reapplique les droits du compte applicatif.
 *
 * A lancer apres toute migration qui cree une table : MySQL n'accorde aucun
 * droit sur une table nouvelle. Doit tourner sous le compte PROPRIETAIRE.
 *
 * `--database` vise une autre base que celle en service : c'est ce dont la
 * restauration a besoin, les droits n'etant PAS dans la sauvegarde (MySQL les
 * range dans la base systeme `mysql`, pas dans celle du projet).
 */
class ApplyPrivileges extends Command
{
    protected $signature = 'phoenix:droits {--connection=mysql_owner} {--database=}';

    protected $description = 'Reapplique les droits du compte applicatif, table par table';

    public function handle(): int
    {
        $connexion = $this->connexionVisee();
        $n = ApplicationPrivileges::apply($connexion);

        $this->info("Droits appliqués sur {$n} table(s).");
        $this->line('  Ajout seul  : '.implode(', ', ApplicationPrivileges::APPEND_ONLY));
        $this->line('  Lecture seule : '.implode(', ', ApplicationPrivileges::READ_ONLY));

        return self::SUCCESS;
    }

    /** La connexion a utiliser, eventuellement redirigee vers une autre base. */
    private function connexionVisee(): string
    {
        $connexion = (string) $this->option('connection');
        $base = $this->option('database');

        if (! is_string($base) || $base === '') {
            return $connexion;
        }

        Config::set('database.connections.droits', array_merge(
            config("database.connections.{$connexion}"),
            ['database' => $base],
        ));

        DB::purge('droits');

        $this->line("Base : {$base}");

        return 'droits';
    }
}
