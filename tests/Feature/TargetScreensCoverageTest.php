<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chaque maquette du dossier cible est servie, ou explicitement ecartee.
 *
 * POURQUOI CE FICHIER EXISTE. « Toutes les interfaces du dossier cible
 * ont-elles ete utilisees ? » etait une question a laquelle je repondais en
 * relisant un script jetable et une entree de journal. Les deux se periment :
 * une maquette construite ne se raye pas toute seule, et j'ai deja annonce
 * deux ecrans manquants qui existaient (D-089), puis decrit un obstacle
 * juridique sans avoir ouvert les fichiers (D-098).
 *
 * La question a desormais une reponse mecanique. Toute maquette du dossier
 * doit etre SERVIE par une route, ou figurer dans ECARTEES avec sa raison.
 * Une maquette ajoutee demain, ou une route retiree, fait echouer ce test.
 */
class TargetScreensCoverageTest extends TestCase
{
    private const DOSSIER = 'new pages';

    /**
     * La maquette, et la route qui la sert.
     *
     * @var array<string, string>
     */
    private const SERVIES = [
        'index.html' => 'home',
        'signup.html' => 'register',
        'login.html' => 'login',
        // Un seul formulaire de connexion pour tous les roles : le role se lit
        // sur le compte, pas sur l'URL. Une porte « agents » distincte
        // n'ajouterait aucune securite et dirait a un inconnu ou frapper.
        'officer-login.html' => 'login',
        'forgot-password.html' => 'password.request',
        'applicant-dashboard.html' => 'dashboard',
        'admin-dashboard.html' => 'dashboard',
        'requests.html' => 'citizen.requests.index',
        'request-detail.html' => 'citizen.requests.show',
        'request-complement.html' => 'citizen.requests.complement',
        'form.html' => 'citizen.requests.step',
        'payments.html' => 'citizen.requests.payment',
        'notifications.html' => 'notifications.index',
        'profile.html' => 'citizen.profile.edit',
        'officer-dashboard.html' => 'officer.queue',
        'officer-verification.html' => 'officer.verification.step',
        'officer-reports.html' => 'officer.reports',
        'mayor-dashboard.html' => 'mayor.dashboard',
        'mayor-sign.html' => 'mayor.review',
        'mayor-signed.html' => 'mayor.signed',
        'admin-agents.html' => 'admin.users.index',
        'admin-centers.html' => 'admin.centers.index',
        'admin-audit.html' => 'admin.audit.index',
        'admin-settings.html' => 'admin.settings.index',
        'admin-payments.html' => 'admin.payments.index',
        'verify.html' => 'verify.show',
        'track.html' => 'track.show',
        'help.html' => 'help',
        'terms.html' => 'legal.terms',
        'privacy.html' => 'legal.privacy',
        'accessibility.html' => 'legal.accessibility',
    ];

    /**
     * Les maquettes sans route, et POURQUOI.
     *
     * Une page d'erreur n'a pas de route : elle est rendue par le gestionnaire
     * d'exceptions. C'est la seule categorie restante, et je l'ai deja annoncee
     * a tort comme « manquante » en D-089 parce que mon releve cherchait au
     * mauvais endroit.
     *
     * @var array<string, string>
     */
    private const ECARTEES = [
        '404.html' => 'errors/404.blade.php',
        'session-expired.html' => 'errors/419.blade.php',
        'maintenance.html' => 'errors/503.blade.php',
    ];

    #[Test]
    public function chaque_maquette_est_servie_ou_ecartee_avec_sa_raison(): void
    {
        $maquettes = [];

        foreach (glob(base_path(self::DOSSIER).'/*.html') ?: [] as $fichier) {
            $maquettes[] = basename($fichier);
        }

        sort($maquettes);

        $declarees = array_merge(array_keys(self::SERVIES), array_keys(self::ECARTEES));
        sort($declarees);

        $this->assertSame(
            $maquettes,
            $declarees,
            'Une maquette du dossier « '.self::DOSSIER." » n'est ni servie ni écartée. "
            .'Ajoutez-la à SERVIES avec sa route, ou à ECARTEES avec sa raison.'
        );
    }

    #[Test]
    public function chaque_route_annoncee_existe_vraiment(): void
    {
        $manquantes = [];

        foreach (self::SERVIES as $maquette => $route) {
            if (! Route::has($route)) {
                $manquantes[] = "{$maquette} → {$route}";
            }
        }

        $this->assertSame([], $manquantes, "Une maquette annonce une route qui n'existe plus :\n".implode("\n", $manquantes));
    }

    #[Test]
    public function chaque_gabarit_ecarte_existe_vraiment(): void
    {
        foreach (self::ECARTEES as $maquette => $vue) {
            $this->assertFileExists(
                resource_path('views/'.$vue),
                "{$maquette} est écartée au motif que « {$vue} » la rend, mais ce fichier n'existe pas."
            );
        }
    }
}
