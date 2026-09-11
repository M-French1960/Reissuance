<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * MySQL est le seul moteur supporte, et l'application le fait respecter.
 *
 * POURQUOI CE CONTROLE EXISTE. Toutes les barrieres anti-fraude de PHOENIX
 * sont posees DANS la base : declencheurs de machine a etats, contraintes
 * CHECK, exigence d'une ligne d'audit dans la meme transaction, et droits
 * accordes table par table qui rendent le journal d'audit inaltérable. Aucune
 * ne survit a un changement de moteur.
 *
 * Sur SQLite, par exemple, l'application demarrerait, les ecrans
 * fonctionneraient, et une transition interdite serait acceptee sans que rien
 * ne le signale. C'est la pire des pannes : silencieuse, et du cote de la
 * permissivite.
 *
 * POURQUOI ELAGUER LES CONNEXIONS ICI ET PAS DANS config/database.php.
 * Verifie, pas suppose : les retirer du fichier ne les supprime pas. Laravel
 * fusionne sa configuration de base avec celle de l'application, et
 * `connections` fait partie de ses options fusionnables
 * (LoadConfiguration::mergeableOptions). Les connexions sqlite, mariadb,
 * pgsql et sqlsrv du cadre revenaient donc toutes, le fichier du projet vide
 * ou non. L'elagage a lieu ici, ou il a reellement lieu.
 *
 * Les connexions creees APRES le demarrage — `verification` de
 * phoenix:verifier-restauration, `droits` de phoenix:droits — ne sont pas
 * concernees : elles sont derivees de `mysql` ou `mysql_owner`, donc MySQL par
 * construction.
 *
 * Voir D-054.
 */
final class DatabaseEngineGuard
{
    /** Le pilote, et le seul. */
    public const DRIVER = 'mysql';

    /**
     * Les connexions du projet. Toute autre est retiree au demarrage.
     *
     * @var list<string>
     */
    public const CONNECTIONS = ['mysql', 'mysql_owner'];

    /** @throws RuntimeException si la configuration sort de MySQL */
    public static function enforce(): void
    {
        self::pruneForeignConnections();
        self::assertDefaultIsMysql();
        self::assertAllDriversAreMysql();
    }

    /** Retire les connexions que le cadre reinjecte. */
    private static function pruneForeignConnections(): void
    {
        $connexions = (array) Config::get('database.connections', []);

        $retenues = array_intersect_key(
            $connexions,
            array_flip(self::CONNECTIONS),
        );

        if ($retenues !== $connexions) {
            Config::set('database.connections', $retenues);
        }
    }

    private static function assertDefaultIsMysql(): void
    {
        $defaut = (string) Config::get('database.default');

        if (in_array($defaut, self::CONNECTIONS, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            "Connexion par défaut inattendue : « %s ».\n".
            'PHOENIX ne fonctionne que sur MySQL : les déclencheurs de machine à états, '
            ."les contraintes CHECK et le journal d'audit en ajout seul sont posés dans la "
            ."base et ne survivent à aucun autre moteur.\n"
            .'Corrigez DB_CONNECTION dans .env — valeurs acceptées : %s.',
            $defaut,
            implode(', ', self::CONNECTIONS),
        ));
    }

    private static function assertAllDriversAreMysql(): void
    {
        foreach ((array) Config::get('database.connections', []) as $nom => $reglages) {
            $pilote = is_array($reglages) ? ($reglages['driver'] ?? null) : null;

            if ($pilote === self::DRIVER) {
                continue;
            }

            throw new RuntimeException(sprintf(
                "La connexion « %s » utilise le pilote « %s » au lieu de « %s ».\n".
                'Les barrières anti-fraude de PHOENIX sont posées dans la base et ne '
                .'survivent pas à un changement de moteur (D-054).',
                $nom,
                is_string($pilote) ? $pilote : 'inconnu',
                self::DRIVER,
            ));
        }
    }
}
