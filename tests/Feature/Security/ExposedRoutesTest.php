<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce qui est joignable sans session, et par quel chemin les fichiers sortent.
 *
 * Ce test est structurel : il interroge la table de routage plutot que
 * d'enumerer a la main des URL connues. Une route ajoutee demain sans filtre
 * le fait echouer, ce qu'une liste ecrite a la main ne ferait pas.
 *
 * Il est ne d'un vrai defaut : le disque « local » du squelette Laravel est
 * enracine dans storage/app/private AVEC 'serve' => true, ce qui publiait une
 * route GET /storage/{path} sur le repertoire des pieces d'identite et des
 * actes signes. Elle exigeait une URL signee, donc rien ne fuyait ; mais elle
 * servait le fichier sans consulter la Policy ni journaliser la lecture.
 */
class ExposedRoutesTest extends TestCase
{
    /**
     * Les seules routes joignables sans authentification.
     *
     * Toute autre route GET doit porter le filtre `auth`. Cette liste est
     * volontairement courte et explicite : l'allonger doit etre un acte
     * delibere, visible en revue.
     *
     * @var list<string>
     */
    private const PUBLIQUES = [
        'home',
        'health',
        'login',
        'register',
        'password.request',
        'password.reset',
        'two-factor.login',
        'dev.ui',
    ];

    #[Test]
    public function aucune_route_get_hors_liste_n_est_joignable_sans_session(): void
    {
        $ouvertes = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $protegee = array_filter($middleware, fn ($m): bool => is_string($m)
                && (str_starts_with($m, 'auth') || str_starts_with($m, 'guest') || $m === 'signed'));

            if ($protegee !== []) {
                continue;
            }

            $nom = $route->getName();

            if ($nom !== null && in_array($nom, self::PUBLIQUES, true)) {
                continue;
            }

            // La sonde de sante du squelette, sans nom et sans contenu metier.
            if ($route->uri() === 'up') {
                continue;
            }

            $ouvertes[] = ($nom ?? '(sans nom)').' — /'.$route->uri();
        }

        $this->assertSame([], $ouvertes, implode("\n", array_merge(
            ['Ces routes GET sont joignables sans aucune session :'],
            $ouvertes,
            ['Ajoutez un filtre, ou inscrivez-les dans self::PUBLIQUES en connaissance de cause.'],
        )));
    }

    /**
     * Aucune route ne doit servir un fichier depuis le stockage prive.
     *
     * C'est le garde-fou n5 verifie a la racine plutot qu'a l'usage : peu
     * importe quelle Policy protege quel controleur, si un disque servi par
     * URL pointe sur le repertoire, la question ne se pose meme plus.
     */
    #[Test]
    public function aucun_disque_servi_par_url_ne_pointe_sur_le_stockage_prive(): void
    {
        $prive = realpath(storage_path('app/private'));
        $this->assertNotFalse($prive, "Le repertoire de stockage prive n'existe pas.");

        $fautifs = [];

        foreach (config('filesystems.disks') as $nom => $config) {
            if (($config['driver'] ?? null) !== 'local' || ! ($config['serve'] ?? false)) {
                continue;
            }

            $racine = realpath($config['root'] ?? '');

            if ($racine !== false && ($racine === $prive || str_starts_with($prive, $racine.DIRECTORY_SEPARATOR))) {
                $fautifs[] = "{$nom} — {$config['root']}";
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['Ces disques sont servis par URL et couvrent le stockage prive :'],
            $fautifs,
            ["Une URL signee suffirait alors a lire une piece d'identite sans passer par la Policy ni par le journal."],
        )));
    }

    #[Test]
    public function le_disque_prive_n_est_pas_servi_et_n_expose_aucune_url(): void
    {
        $this->assertFalse(config('filesystems.disks.private.serve', false));
        $this->assertArrayNotHasKey('url', config('filesystems.disks.private'));
        $this->assertNull(config('filesystems.disks.private.visibility'));
    }

    /**
     * Le repli du disque par defaut ne doit pas etre un disque servi.
     *
     * Une variable d'environnement absente ne doit jamais ouvrir un acces :
     * c'est le mode de defaillance le plus banal d'un deploiement.
     */
    #[Test]
    public function le_disque_par_defaut_reste_prive_sans_variable_d_environnement(): void
    {
        $contenu = file_get_contents(config_path('filesystems.php'));

        $this->assertMatchesRegularExpression(
            "/'default'\s*=>\s*env\('FILESYSTEM_DISK',\s*'private'\)/",
            $contenu,
            "Le repli du disque par defaut doit etre 'private'."
        );
    }

    /**
     * Les documents produits ne sont jamais ecrits sous public/.
     */
    #[Test]
    public function la_racine_web_ne_contient_aucun_acte_ni_aucune_piece(): void
    {
        $suspects = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fichier) {
            $chemin = str_replace(public_path().DIRECTORY_SEPARATOR, '', $fichier->getPathname());

            if (Str::startsWith($chemin, ['acts'.DIRECTORY_SEPARATOR, 'identity'.DIRECTORY_SEPARATOR, 'storage'.DIRECTORY_SEPARATOR])) {
                $suspects[] = $chemin;
            }
        }

        $this->assertSame([], $suspects, 'Des documents sont accessibles directement sous public/ : '.implode(', ', $suspects));
    }
}
