<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Lecture des droits MySQL du compte applicatif, pour les tests de securite.
 *
 * TROIS PIEGES, tous rencontres en ecrivant ces tests, tous silencieux :
 *
 * 1. `SHOW GRANTS` renvoie les droits de TOUTES les bases. Sans filtrer sur la
 *    base courante, un test lancé sur `phoenix_test` lisait aussi les droits de
 *    `phoenix` : un droit laxiste en developpement aurait fait echouer la suite,
 *    et — bien pire — l'ordre des lignes decidait lequel gagnait.
 *
 * 2. `information_schema.TABLES` est filtree par les droits du lecteur. Le
 *    compte applicatif ne voit PAS une table sur laquelle il n'a aucun droit :
 *    demander « quelles tables ont ete oubliees ? » a ce compte renvoie
 *    toujours « aucune ». La liste des tables se lit donc sous le compte
 *    proprietaire.
 *
 * 3. Les droits de base et de table s'additionnent (D-051) : lire les seuls
 *    droits de table ne suffit pas a conclure. Le test dedie verifie
 *    separement qu'aucun droit de base n'existe.
 */
trait ReadsDatabaseGrants
{
    /**
     * Les droits du compte applicatif, table par table, sur la base COURANTE.
     *
     * @return array<string, list<string>>
     */
    protected static function droitsParTable(): array
    {
        $base = (string) DB::connection()->getDatabaseName();
        $parTable = [];

        foreach (self::lignesDeGrants() as $ligne) {
            if (! preg_match('/^GRANT (.+?) ON `([^`]+)`\.`([^`]+)` TO /', $ligne, $trouve)) {
                continue;
            }

            if ($trouve[2] !== $base) {
                continue;
            }

            $parTable[$trouve[3]] = array_map(trim(...), explode(',', $trouve[1]));
        }

        return $parTable;
    }

    /**
     * Les lignes brutes de SHOW GRANTS, toutes bases confondues.
     *
     * @return list<string>
     */
    protected static function lignesDeGrants(): array
    {
        return array_map(
            static function (object $ligne): string {
                $colonnes = array_values((array) $ligne);

                return (string) ($colonnes[0] ?? '');
            },
            DB::select('SHOW GRANTS FOR CURRENT_USER()'),
        );
    }

    /**
     * Les tables de la base, lues sous le compte PROPRIETAIRE.
     *
     * @return list<string>
     */
    protected static function tablesDeLaBase(): array
    {
        $lignes = DB::connection('mysql_owner')->select(
            "SELECT TABLE_NAME AS nom FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME",
            [DB::connection()->getDatabaseName()],
        );

        return array_map(static fn (object $l): string => (string) $l->nom, $lignes);
    }
}
