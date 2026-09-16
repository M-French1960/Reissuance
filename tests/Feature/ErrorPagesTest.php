<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les pages d'erreur sont des pages du service.
 *
 * CE QUE CES TESTS FERMENT (D-073). Aucune page d'erreur n'existait : Laravel
 * rendait sa page par defaut, sans en-tete, sans pied de page et sans un mot
 * de francais — un ecran blanc portant « Not Found ». Un citoyen qui se trompe
 * d'adresse, ou qui suit un lien peri, se retrouvait sans rien : ni
 * explication, ni chemin de retour.
 *
 * Trouve en parcourant les ecrans un a un, pas par un test : la suite
 * verifiait les refus par leur CODE HTTP, jamais par ce qu'ils affichent.
 */
class ErrorPagesTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function pagesDErreur(): iterable
    {
        yield '404' => ['404'];
        yield '403' => ['403'];
        yield '419' => ['419'];
        yield '500' => ['500'];
        yield '503' => ['503'];
    }

    #[Test]
    #[DataProvider('pagesDErreur')]
    public function chaque_page_d_erreur_existe_et_parle_francais(string $code): void
    {
        $vue = view("errors.{$code}", ['exception' => new \Exception('essai')])->render();

        $this->assertNotEmpty(trim(strip_tags($vue)));
        // Un chemin de retour, toujours : une page d'erreur sans issue est une
        // impasse.
        $this->assertStringContainsString(route('home'), $vue);
    }

    /** Une adresse inconnue rend une VRAIE page, pas un écran blanc. */
    #[Test]
    public function une_adresse_inconnue_rend_une_page_utile(): void
    {
        $reponse = $this->get('/cette-adresse-nexiste-pas');

        $reponse->assertNotFound();
        $reponse->assertSee('Page introuvable');
        $reponse->assertSee(route('home'));
        // L'en-tête et le pied de page du service, pour ne pas sortir du site.
        $reponse->assertSee('PHOENIX');
    }

    /** Connecté, le retour mène à son espace plutôt qu'à l'accueil public. */
    #[Test]
    public function un_utilisateur_connecte_est_ramene_a_son_espace(): void
    {
        $reponse = $this->actingAs(User::factory()->citizen()->create())
            ->get('/cette-adresse-nexiste-pas');

        $reponse->assertNotFound();
        $reponse->assertSee(route('dashboard'));
    }

    /**
     * ET ELLE N'APPREND RIEN.
     *
     * Un 404 servi a la place d'un 403 ne doit dire ni qu'un dossier existe,
     * ni pourquoi l'acces est refuse.
     */
    #[Test]
    public function la_page_introuvable_n_apprend_rien_sur_ce_qui_existe(): void
    {
        $contenu = (string) $this->get('/mon-espace/demandes/999999')->getContent();

        foreach (['999999', 'existe', 'appartient', 'autorisation', 'Policy'] as $indice) {
            $this->assertStringNotContainsString(
                $indice,
                $contenu,
                "La page introuvable laisse filtrer « {$indice} »."
            );
        }
    }
}
