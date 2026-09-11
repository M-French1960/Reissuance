<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Droits du compte applicatif, table par table.
 *
 * POURQUOI TABLE PAR TABLE, et pas sur la base entiere : en MySQL, les droits
 * de base et de table **s'additionnent**. Un `GRANT ... ON phoenix.*` suivi
 * d'un `REVOKE ... ON phoenix.audit_logs` ne revoque RIEN — le droit de base
 * subsiste. Verifie, pas suppose : la revocation par table sur un droit de
 * base est acceptee sans effet.
 *
 * C'est la difference la plus consequente avec PostgreSQL, ou chaque objet
 * porte ses propres droits. Voir D-051.
 *
 * Deuxieme consequence : MySQL n'a pas d'equivalent d'ALTER DEFAULT
 * PRIVILEGES. Une table creee plus tard ne recoit AUCUN droit. Ces droits se
 * reappliquent donc apres toute migration qui cree une table —
 * `php artisan phoenix:droits` — et un test verifie qu'aucune table n'a ete
 * oubliee.
 */
final class ApplicationPrivileges
{
    /**
     * Tables en AJOUT SEUL pour le compte applicatif.
     *
     * `audit_logs` : le coeur du dispositif anti-fraude (§4.4 du brief).
     * `allowed_transitions` : referentiel, modifiable par migration seulement.
     *
     * @var list<string>
     */
    public const APPEND_ONLY = ['audit_logs'];

    /** @var list<string> */
    public const READ_ONLY = ['allowed_transitions'];

    /**
     * Vues lisibles par le compte applicatif.
     *
     * Elles ne sont pas des BASE TABLE : la boucle principale ne les voit
     * pas, et une vue oubliee ici perdrait son droit au prochain
     * `phoenix:droits`. Voir la migration 2026_01_11_000200.
     *
     * @var list<string>
     */
    public const READABLE_VIEWS = ['phoenix_guards'];

    /**
     * Codes MySQL signifiant « aucun droit a revoquer ».
     *
     * 1141 : cible de base (`base`.*). 1147 : cible de table.
     *
     * @var list<int>
     */
    private const NOTHING_TO_REVOKE = [1141, 1147];

    /** Applique les droits sur toutes les tables existantes. */
    public static function apply(?string $connection = null): int
    {
        $connexion = DB::connection($connection);
        $base = (string) $connexion->getDatabaseName();
        $compte = self::applicationAccount();

        $tables = $connexion->select(
            'SELECT TABLE_NAME AS nom FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'',
            [$base]
        );

        foreach ($tables as $table) {
            $nom = (string) $table->nom;
            self::assertIdentifier($nom);

            // On repart de zero sur la table : un droit accorde par une
            // execution precedente ne doit pas survivre a un changement de
            // classement.
            self::revokeIfGranted($connexion, $base, $nom, $compte);

            $droits = match (true) {
                in_array($nom, self::READ_ONLY, true) => 'SELECT',
                in_array($nom, self::APPEND_ONLY, true) => 'SELECT, INSERT',
                default => 'SELECT, INSERT, UPDATE, DELETE',
            };

            $connexion->statement("GRANT {$droits} ON `{$base}`.`{$nom}` TO {$compte}");
        }

        // Les vues, traitees a part : information_schema.TABLES les classe en
        // « VIEW », pas en « BASE TABLE ».
        foreach (self::READABLE_VIEWS as $vue) {
            self::assertIdentifier($vue);

            $existe = $connexion->selectOne(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = \'VIEW\'',
                [$base, $vue]
            );

            if ($existe !== null) {
                $connexion->statement("GRANT SELECT ON `{$base}`.`{$vue}` TO {$compte}");
            }
        }

        // Aucun droit sur la base elle-meme : il s'AJOUTERAIT aux droits de
        // table et rendrait le journal modifiable a nouveau.
        self::revokeIfGranted($connexion, $base, null, $compte);

        // Pas de FLUSH PRIVILEGES : GRANT et REVOKE mettent a jour les tables
        // de droits en memoire immediatement. FLUSH n'est utile qu'apres une
        // ecriture directe dans `mysql.user`, et il exige le droit global
        // RELOAD que le compte proprietaire n'a pas — a raison : ce compte est
        // limite a la base du projet.
        return count($tables);
    }

    /**
     * Revoque, en tolerant l'absence de droit a revoquer.
     *
     * MySQL distingue deux codes pour « il n'y avait rien a revoquer » :
     * 1147 pour une cible de table, 1141 pour une cible de base. Les deux
     * sont le cas normal a la premiere execution. On ne rattrape QUE ces
     * deux erreurs-la : toute autre remonte, parce qu'un droit qu'on croit
     * revoque et qui ne l'est pas est exactement le defaut qu'on cherche a
     * eviter.
     */
    private static function revokeIfGranted(
        Connection $connexion,
        string $base,
        ?string $table,
        string $compte,
    ): void {
        $cible = $table === null ? "`{$base}`.*" : "`{$base}`.`{$table}`";

        try {
            $connexion->statement("REVOKE ALL PRIVILEGES ON {$cible} FROM {$compte}");
        } catch (QueryException $e) {
            // 1147 (table) et 1141 (base) : « There is no such grant
            // defined ». Rien a revoquer.
            if (! in_array($e->errorInfo[1] ?? null, self::NOTHING_TO_REVOKE, true)) {
                throw $e;
            }
        }
    }

    /** Le compte applicatif, cite pour MySQL : 'nom'@'hote'. */
    public static function applicationAccount(): string
    {
        $nom = (string) config('database.connections.mysql.username');
        self::assertIdentifier($nom);

        // L'hote depend du mode de connexion : une socket unix arrive comme
        // « localhost », une adresse TCP comme l'adresse vue par le serveur.
        $hote = filled(config('database.connections.mysql.unix_socket'))
            ? 'localhost'
            : (string) config('database.connections.mysql.host', '127.0.0.1');

        return "'{$nom}'@'{$hote}'";
    }

    /**
     * Un identifiant interpole dans du SQL n'est jamais pris tel quel.
     *
     * Ces valeurs viennent de la configuration, pas d'une requete HTTP, mais
     * une base nommee depuis une variable d'environnement reste une entree.
     */
    private static function assertIdentifier(string $valeur): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $valeur)) {
            throw new RuntimeException("Identifiant SQL invalide : {$valeur}");
        }
    }
}
