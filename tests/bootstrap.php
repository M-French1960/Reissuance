<?php

declare(strict_types=1);

/*
 * Amorcage de la suite : la base d'essai est migree AVANT le premier test.
 *
 * POURQUOI ICI, et pas dans TestCase::setUp(). La suite s'execute sous le
 * compte applicatif, qui n'a aucun droit tant que les migrations n'ont pas
 * accorde les siens. Or DatabaseTransactions ouvre sa transaction des le debut
 * de setUp() — donc avant toute migration lancee depuis setUp(). Sur une base
 * vierge, chaque test echouait sur « Access denied for user 'phoenix_app' », la
 * migration n'ayant jamais eu l'occasion de tourner.
 *
 * POURQUOI UN SOUS-PROCESSUS, et pas le noyau console charge ici meme. Amorcer
 * Laravel dans ce fichier installe ses gestionnaires d'erreurs et d'exceptions.
 * Chaque test les retire ensuite avec les siens, et PHPUnit signale alors
 * « removed error handlers other than its own » : toute la suite passait en
 * « risky ». Un test douteux qu'on apprend a ignorer ne signale plus rien.
 * Le sous-processus laisse l'etat du processus de test intact.
 */

require __DIR__.'/../vendor/autoload.php';

/*
 * Garde-fou : ce fichier lance un `migrate:fresh`, qui VIDE la base visee.
 *
 * Il a ete ecrit apres l'avoir lance a la main hors de PHPUnit : sans les
 * variables d'environnement de phpunit.xml, la cible n'etait plus la base
 * d'essai mais la base de developpement, qui a ete videe. Un outil de test qui
 * peut detruire des donnees de travail par simple erreur d'invocation est un
 * outil dangereux, quelle que soit la prudence de qui l'utilise.
 */
$cible = getenv('DB_DATABASE');

if (getenv('APP_ENV') !== 'testing' || ! is_string($cible) || ! str_ends_with($cible, '_test')) {
    fwrite(STDERR, sprintf(
        "Refus : ce fichier vide la base visee et n'est destine qu'a PHPUnit.\n".
        "  APP_ENV      = %s (attendu : testing)\n".
        "  DB_DATABASE  = %s (attendu : un nom terminant par _test)\n".
        "Lancez la suite avec `php artisan test` ou `vendor/bin/phpunit`.\n",
        var_export(getenv('APP_ENV'), true),
        var_export($cible, true),
    ));
    exit(1);
}

$commande = sprintf(
    '%s %s migrate:fresh --database=mysql_owner --force 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg(__DIR__.'/../artisan'),
);

exec($commande, $sortie, $code);

if ($code !== 0) {
    fwrite(STDERR, "Migration de la base d'essai impossible :\n".implode("\n", $sortie)."\n");
    exit(1);
}
