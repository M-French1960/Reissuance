<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\CivilStatusCenter;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * R14 : un compte suspendu perd la main IMMEDIATEMENT, sur toutes les routes.
 *
 * Le filtre `active` etait pose sur le groupe de routes applicatives, pas sur
 * celles que Fortify enregistre lui-meme. Un agent suspendu gardait donc acces
 * a la gestion de sa propre double authentification : il pouvait la
 * reinitialiser, lire sa cle secrete et regenerer ses codes de secours pendant
 * sa suspension.
 */
class AccountLifecycleTest extends TestCase
{
    /**
     * Les routes de Fortify joignables avec une session, et ce qu'un compte
     * suspendu ne doit plus pouvoir y faire.
     *
     * @return list<array{string, string}>
     */
    public static function routesDeCompte(): array
    {
        return [
            'cle secrete 2FA' => ['GET', 'user/two-factor-secret-key'],
            'codes de secours' => ['GET', 'user/two-factor-recovery-codes'],
            'QR code 2FA' => ['GET', 'user/two-factor-qr-code'],
            'etat de confirmation du mot de passe' => ['GET', 'user/confirmed-password-status'],
        ];
    }

    #[DataProvider('routesDeCompte')]
    #[Test]
    public function un_compte_suspendu_perd_aussi_les_routes_de_fortify(string $methode, string $uri): void
    {
        $officier = User::factory()->officer(CivilStatusCenter::factory()->create())->create();

        $this->actingAs($officier);
        $this->session(['auth.password_confirmed_at' => time()]);

        $officier->forceFill(['status' => 'suspended'])->save();

        $reponse = $this->call($methode, $uri);

        $this->assertNotSame(
            200,
            $reponse->status(),
            "Un compte suspendu atteint encore {$methode} /{$uri}."
        );
    }

    /**
     * La verification structurelle, qui survivra a l'ajout d'une route.
     *
     * Enumerer quatre URL connues ne protege pas de la cinquieme. On exige
     * donc que TOUTE route authentifiee porte le filtre.
     */
    #[Test]
    public function toute_route_authentifiee_porte_le_filtre_de_compte_actif(): void
    {
        $sans = [];
        // Le routeur n'apprend ses groupes qu'au demarrage du noyau HTTP.
        // Un test qui n'a encore emis aucune requete les lirait vides, et
        // passerait alors pour de mauvaises raisons.
        $groupes = app(Kernel::class)->getMiddlewareGroups();

        foreach (Route::getRoutes() as $route) {
            // gatherMiddleware() rend les NOMS de groupe, pas leur contenu :
            // sans cette expansion, un filtre pose sur le groupe `web` serait
            // invisible et le test passerait pour de mauvaises raisons.
            $middleware = [];

            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && isset($groupes[$m])) {
                    $middleware = array_merge($middleware, $groupes[$m]);

                    continue;
                }

                $middleware[] = $m;
            }

            $authentifiee = array_filter(
                $middleware,
                fn ($m): bool => is_string($m) && str_starts_with($m, 'auth')
            );

            if ($authentifiee === []) {
                continue;
            }

            $porteLeFiltre = array_filter(
                $middleware,
                fn ($m): bool => $m === 'active' || $m === EnsureAccountIsActive::class
            );

            if ($porteLeFiltre === []) {
                $sans[] = implode('|', $route->methods()).' /'.$route->uri();
            }
        }

        $this->assertSame([], $sans, implode("\n", array_merge(
            ['Ces routes authentifiees ne verifient pas le statut du compte :'],
            $sans,
            ['Un compte suspendu y garderait la main jusqu\'a sa deconnexion.'],
        )));
    }
}
