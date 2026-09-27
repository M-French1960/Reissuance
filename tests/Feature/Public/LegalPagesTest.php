<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Http\Controllers\LegalPageController;
use App\Services\AttachmentRetention;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les trois pages juridiques (D-098).
 *
 * CE QUE CES TESTS GARDENT, et ce n'est pas la mise en page : que ces pages
 * ne se fassent JAMAIS passer pour le document juridique. Les maquettes du
 * client etaient vides et renvoyaient la redaction a un juriste ; le risque
 * n'est pas qu'elles restent vides, c'est qu'un jour quelqu'un retire
 * l'avertissement en croyant « finir » la page.
 */
class LegalPagesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function lesTroisPages(): iterable
    {
        foreach (array_keys(LegalPageController::PAGES) as $page) {
            yield $page => [$page];
        }
    }

    #[Test]
    #[DataProvider('lesTroisPages')]
    public function la_page_est_lisible_sans_compte(string $page): void
    {
        $this->get(route("legal.{$page}"))
            ->assertOk()
            ->assertSee(__("public.legal.{$page}.title"));
    }

    /**
     * L'AVERTISSEMENT EST LA CONDITION D'EXISTENCE DE CES PAGES. Sans lui,
     * elles se liraient comme des conditions d'utilisation, ce qu'elles ne
     * sont pas et ce que le 10 du brief interdit de simuler.
     */
    #[Test]
    #[DataProvider('lesTroisPages')]
    public function la_page_dit_qu_elle_n_est_pas_le_document_juridique(string $page): void
    {
        $this->get(route("legal.{$page}"))
            ->assertOk()
            ->assertSee(__('public.legal.notice_title'))
            ->assertSee('lawyer', false)
            ->assertSee('commits the administration', false);
    }

    /** Chaque point annonce doit exister : une page a demi traduite se verrait. */
    #[Test]
    #[DataProvider('lesTroisPages')]
    public function tous_les_points_annonces_sont_servis(string $page): void
    {
        $reponse = $this->get(route("legal.{$page}"))->assertOk();
        ['faits' => $faits, 'ouvert' => $ouvert] = LegalPageController::PAGES[$page];

        for ($i = 1; $i <= $faits; $i++) {
            $cle = "public.legal.{$page}.fact_{$i}";
            $this->assertNotSame($cle, __($cle), "Le fait {$i} de {$page} n'est pas traduit.");
            $reponse->assertSee(__($cle), false);
        }

        for ($i = 1; $i <= $ouvert; $i++) {
            $cle = "public.legal.{$page}.open_{$i}";
            $this->assertNotSame($cle, __($cle), "Le point ouvert {$i} de {$page} n'est pas traduit.");
            $reponse->assertSee(__($cle), false);
        }
    }

    /**
     * LES FAITS ANNONCES DOIVENT ETRE VRAIS. Ces pages promettent que chaque
     * point est tenu par un test ; deux d'entre eux se verifient ici meme, et
     * les autres ont leurs propres fichiers. Si l'un devient faux, cette page
     * ment a quelqu'un qui la lit pour decider s'il confie sa piece d'identite.
     */
    #[Test]
    public function le_fait_annonce_sur_la_conservation_est_exact(): void
    {
        // « Aucune duree de conservation n'est configuree sur cette
        // installation » — vrai tant que B3 n'a pas de reponse (D-094).
        config(['phoenix.uploads.retention_days' => null]);

        $this->assertNull(AttachmentRetention::retentionDays());

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('no expiry date', false);
    }

    #[Test]
    public function le_fait_annonce_sur_l_absence_de_valeur_juridique_est_exact(): void
    {
        // « le prestataire de signature est un adaptateur de demonstration »
        $this->assertSame('fake', (string) config('phoenix.providers.signature', 'fake'));

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('no legal value', false);
    }

    /** Une page inconnue leve a la source, plutot que de rendre un ecran vide. */
    #[Test]
    public function une_page_inconnue_est_refusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LegalPageController)('inexistante');
    }

    #[Test]
    #[DataProvider('lesTroisPages')]
    public function les_trois_pages_sont_atteignables_depuis_l_accueil(string $page): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route("legal.{$page}"), false);
    }
}
