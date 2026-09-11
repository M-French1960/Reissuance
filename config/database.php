<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Connexions
    |--------------------------------------------------------------------------
    |
    | DEUX CONNEXIONS, ET UN SEUL MOTEUR. Les connexions livrees par defaut
    | avec Laravel — sqlite, mariadb, pgsql, sqlsrv — ont ete RETIREES, et ce
    | n'est pas du menage : toutes les barrieres anti-fraude de PHOENIX sont
    | posees dans la base elle-meme (declencheurs de machine a etats,
    | contraintes CHECK, droits accordes table par table qui rendent le journal
    | d'audit inaltérable). Aucune ne survit a un changement de moteur.
    |
    | Un `DB_CONNECTION=sqlite` pose par erreur, ou herite d'un fichier .env
    | d'exemple, ferait demarrer une application qui fonctionne, qui passe les
    | ecrans, et dans laquelle une transition interdite serait acceptee et le
    | journal d'audit modifiable. Rien ne le signalerait.
    |
    | ATTENTION : LES RETIRER D'ICI NE LES SUPPRIME PAS. Verifie. Laravel
    | fusionne sa configuration de base avec celle de l'application, et
    | `connections` figure dans ses options fusionnables
    | (LoadConfiguration::mergeableOptions). Les connexions du cadre revenaient
    | donc toutes, ce fichier vide ou non. Ce fichier dit l'intention ;
    | l'application, elle, est faite par DatabaseEngineGuard, qui elague les
    | connexions au demarrage et refuse de demarrer sur un pilote autre que
    | MySQL (D-054).
    |
    | `mysql`       : l'application. Droits table par table, jamais sur la base.
    | `mysql_owner` : les migrations UNIQUEMENT. Voir docs/ARCHITECTURE_LOCAL.md 5.1.
    |
    */

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
         * Connexion PROPRIETAIRE du schema : migrations UNIQUEMENT.
         *
         * L'application ne l'utilise jamais. C'est ce qui permet au journal
         * d'audit d'etre inalterable pour le compte applicatif
         * (docs/ARCHITECTURE_LOCAL.md 5.1).
         *
         * En MySQL, les droits de base et de table S'ADDITIONNENT : on ne peut
         * pas revoquer sur une table ce qui est accorde sur la base. Les
         * droits du compte applicatif sont donc accordes TABLE PAR TABLE
         * (D-051).
         */
        'mysql_owner' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_OWNER_USERNAME', 'phoenix_owner'),
            'password' => env('DB_OWNER_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // Connexion proprietaire du schema : migrations UNIQUEMENT.
        // L'application ne l'utilise jamais (docs/ARCHITECTURE_LOCAL.md 5.1).

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
