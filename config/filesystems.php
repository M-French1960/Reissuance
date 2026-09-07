<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    // Repli sur le disque prive, jamais sur un disque servi par URL : une
    // variable d'environnement absente ne doit pas ouvrir un acces.
    'default' => env('FILESYSTEM_DISK', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Disque prive : hors de public/, jamais servi par URL directe.
        // Toute lecture passe par un controleur qui verifie la Policy avant de
        // servir le premier octet et journalise (D-011, garde-fou n5).
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        /*
         * Disque « local » du squelette Laravel, neutralise.
         *
         * Par defaut il est enracine dans storage/app/private AVEC
         * 'serve' => true, ce qui publie une route GET /storage/{path} sur le
         * repertoire meme ou vivent les pieces d'identite et les actes signes.
         * Cette route est protegee par une signature d'URL, mais elle sert le
         * fichier SANS consulter la Policy et SANS journaliser la lecture :
         * elle contourne exactement le controleur ecrit pour cela, et le seul
         * rempart restant serait l'absence de la cle 'visibility' juste
         * au-dessus du bloc 'public' qui, lui, la porte.
         *
         * On le reenracine ailleurs et on ne le sert pas. Rien dans
         * l'application ne l'utilise (garde-fou n5).
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/local'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
