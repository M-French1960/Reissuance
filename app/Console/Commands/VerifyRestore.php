<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CitizenProfile;
use App\Support\BlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Verifie qu'une base restauree est UTILISABLE, pas seulement peuplee.
 *
 * Compter des lignes ne prouve rien. Une restauration peut rendre le bon
 * nombre d'enregistrements et rester inexploitable :
 *
 *   - sans APP_KEY, les numeros de piece sont chiffres et illisibles ;
 *   - sans la cle d'index aveugle, la recherche par numero ne trouve rien,
 *     SANS erreur — elle rend simplement zero resultat ;
 *   - sans le stockage prive, chaque acte signe designe un fichier absent ;
 *   - sans le declencheur d'etat, la base accepterait des transitions
 *     interdites.
 *
 * Cette commande verifie les quatre.
 *
 * Usage : php artisan phoenix:verifier-restauration --db=phoenix_essai
 */
class VerifyRestore extends Command
{
    protected $signature = 'phoenix:verifier-restauration
                            {--db= : nom de la base a verifier (defaut : celle de la configuration)}';

    protected $description = "Verifie qu'une base restauree est exploitable, pas seulement peuplee";

    public function handle(): int
    {
        $connexion = $this->preparerConnexion();
        $echecs = 0;

        $this->info('Vérification de la restauration');
        $this->newLine();

        $echecs += $this->verifier('Lignes présentes', fn (): string => $this->compter($connexion));
        $echecs += $this->verifier('APP_KEY — un numéro de pièce se déchiffre', fn (): string => $this->dechiffrer($connexion));
        $echecs += $this->verifier("Clé d'index — la recherche par numéro trouve", fn (): string => $this->rechercher($connexion));
        $echecs += $this->verifier('Stockage — les actes signés existent et sont intacts', fn (): string => $this->fichiers($connexion));
        $echecs += $this->verifier("Déclencheur d'état présent", fn (): string => $this->declencheur($connexion));

        $this->newLine();

        if ($echecs > 0) {
            $this->error("{$echecs} vérification(s) en échec. La restauration n'est PAS exploitable.");

            return self::FAILURE;
        }

        $this->info('Restauration exploitable.');

        return self::SUCCESS;
    }

    /** @param callable(): string $controle */
    private function verifier(string $intitule, callable $controle): int
    {
        try {
            $detail = $controle();
            $this->line("  <fg=green>OK</> {$intitule} — {$detail}");

            return 0;
        } catch (Throwable $e) {
            $this->line("  <fg=red>ÉCHEC</> {$intitule} — {$e->getMessage()}");

            return 1;
        }
    }

    /** Ouvre une connexion sur la base nommee, sans toucher a la configuration en service. */
    private function preparerConnexion(): string
    {
        $base = $this->option('db');

        if ($base === null) {
            return 'pgsql';
        }

        Config::set('database.connections.verification', array_merge(
            config('database.connections.pgsql'),
            ['database' => $base],
        ));

        DB::purge('verification');

        $this->line("Base : <options=bold>{$base}</>");

        return 'verification';
    }

    private function compter(string $connexion): string
    {
        $tables = ['reissuance_requests', 'users', 'audit_logs', 'document_signatures', 'request_attachments'];
        $parties = [];

        foreach ($tables as $table) {
            $parties[] = $table.'='.DB::connection($connexion)->table($table)->count();
        }

        if (DB::connection($connexion)->table('users')->count() === 0) {
            throw new \RuntimeException('aucun compte : la base est vide');
        }

        return implode(', ', $parties);
    }

    /**
     * Le chiffrement est symetrique : si APP_KEY differe de celle qui a servi
     * a ecrire, le dechiffrement leve une exception. C'est donc un vrai test.
     */
    private function dechiffrer(string $connexion): string
    {
        $ligne = DB::connection($connexion)->table('citizen_profiles')
            ->whereNotNull('national_id_number')->first();

        if ($ligne === null) {
            return 'aucun profil à vérifier (base sans profil)';
        }

        $profil = (new CitizenProfile)->newFromBuilder((array) $ligne);
        $clair = $profil->national_id_number;

        if (! is_string($clair) || $clair === '') {
            throw new \RuntimeException('déchiffrement vide — APP_KEY ne correspond pas');
        }

        // On ne journalise JAMAIS un numero de piece, meme de demonstration :
        // seule sa longueur est affichee (garde-fou n6).
        return strlen($clair).' caractères déchiffrés';
    }

    /**
     * L'echec silencieux le plus dangereux : avec une mauvaise cle d'index, la
     * recherche ne leve rien et rend zero resultat. Un officier conclurait que
     * la piece est inconnue.
     */
    private function rechercher(string $connexion): string
    {
        $ligne = DB::connection($connexion)->table('citizen_profiles')
            ->whereNotNull('national_id_hash')->first();

        if ($ligne === null) {
            return 'aucun profil indexé à vérifier';
        }

        $profil = (new CitizenProfile)->newFromBuilder((array) $ligne);
        $recalcule = BlindIndex::hash((string) $profil->national_id_number);

        if (! hash_equals($ligne->national_id_hash, $recalcule)) {
            throw new \RuntimeException(
                "l'empreinte recalculée ne correspond pas — PHOENIX_BLIND_INDEX_KEY ne correspond pas"
            );
        }

        $trouve = DB::connection($connexion)->table('citizen_profiles')
            ->where('national_id_hash', $recalcule)->count();

        if ($trouve === 0) {
            throw new \RuntimeException('la recherche par empreinte ne trouve rien');
        }

        return "recherche par numéro concluante ({$trouve} correspondance)";
    }

    /**
     * Le cas que la sauvegarde de la base seule produit : des signatures qui
     * designent des fichiers absents. Le systeme parait coherent et chaque
     * telechargement d'acte echoue.
     */
    private function fichiers(string $connexion): string
    {
        $signatures = DB::connection($connexion)->table('document_signatures')->get();

        if ($signatures->isEmpty()) {
            return 'aucun acte signé à vérifier';
        }

        $disque = Storage::disk('private');
        $absents = [];
        $alteres = [];

        foreach ($signatures as $ligne) {
            if ($ligne->document_path === null) {
                continue;
            }

            if (! $disque->exists($ligne->document_path)) {
                $absents[] = $ligne->id;

                continue;
            }

            $empreinte = hash('sha256', $disque->get($ligne->document_path));

            if (! hash_equals((string) $ligne->document_hash, $empreinte)) {
                $alteres[] = $ligne->id;
            }
        }

        if ($absents !== []) {
            throw new \RuntimeException(
                count($absents).' acte(s) désignent un fichier absent : le stockage privé n\'a pas été restauré'
            );
        }

        if ($alteres !== []) {
            throw new \RuntimeException(count($alteres).' acte(s) ne correspondent plus à leur empreinte');
        }

        return $signatures->count().' acte(s) présents et conformes à leur empreinte';
    }

    /** Sans le declencheur, la base accepterait une transition interdite. */
    private function declencheur(string $connexion): string
    {
        $existe = DB::connection($connexion)->selectOne(
            "SELECT tgname FROM pg_trigger WHERE tgname = 'phoenix_guard_request_status_trigger'"
        );

        if ($existe === null) {
            throw new \RuntimeException('déclencheur absent : la machine à états n\'est plus protégée en base');
        }

        $transitions = DB::connection($connexion)->table('allowed_transitions')->count();

        if ($transitions === 0) {
            throw new \RuntimeException('table allowed_transitions vide : toute transition serait refusée');
        }

        return "déclencheur présent, {$transitions} transitions autorisées";
    }
}
