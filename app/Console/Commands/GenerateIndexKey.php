<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateIndexKey extends Command
{
    protected $signature = 'phoenix:generate-index-key {--show : Afficher la clé sans écrire dans .env}';

    protected $description = "Génère la clé HMAC de l'index aveugle sur le numéro de pièce";

    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $path = base_path('.env');

        if (! is_file($path)) {
            $this->error('.env introuvable.');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);

        if (preg_match('/^PHOENIX_BLIND_INDEX_KEY=.+$/m', $env)) {
            $this->error('Une clé existe déjà. La remplacer rendrait illisibles toutes les');
            $this->error('empreintes déjà enregistrées : la recherche par numéro de pièce');
            $this->error('cesserait de fonctionner. Utiliser --show pour en générer une sans écrire.');

            return self::FAILURE;
        }

        file_put_contents($path, preg_replace(
            '/^PHOENIX_BLIND_INDEX_KEY=.*$/m',
            'PHOENIX_BLIND_INDEX_KEY='.$key,
            $env
        ));

        $this->info('Clé générée et écrite dans .env.');
        $this->warn('À sauvegarder hors de la machine : sa perte rend la recherche par');
        $this->warn('numéro de pièce impossible (docs/ARCHITECTURE_LOCAL.md §7).');

        return self::SUCCESS;
    }
}
