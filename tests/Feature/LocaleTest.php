<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CivilStatusCenter;
use App\Models\User;
use App\Support\Locales;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The platform serves both official languages (D-076).
 *
 * Cameroon has two official languages of equal standing. Serving one and
 * printing raw translation keys in the other would leave half the country
 * reading `citizen.tracking.rejected_title` where the reason their certificate
 * was refused should be.
 */
class LocaleTest extends TestCase
{
    /** The default is the platform's, and it is English. */
    #[Test]
    public function un_visiteur_sans_preference_recoit_la_langue_par_defaut(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Your lost birth certificate')
            ->assertSee('<html lang="en"', false);
    }

    /** A visitor can switch, and the switch holds across pages. */
    #[Test]
    public function un_visiteur_peut_changer_de_langue_et_le_choix_tient(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Votre acte de naissance perdu', false)
            ->assertSee('<html lang="fr"', false);

        // Et sur une autre page, sans avoir a rechoisir.
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
    }

    /** An unsupported code is refused rather than serving no language at all. */
    #[Test]
    public function une_langue_inconnue_est_refusee(): void
    {
        $this->post(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');

        $this->get(route('home'))->assertOk()->assertSee('<html lang="en"', false);
    }

    /**
     * THE ACCOUNT PREFERENCE APPLIES WHEN NOTHING WAS CHOSEN THIS SESSION.
     *
     * That is the shared-counter case: a fresh session carries no choice, so
     * an agent who set French once gets French.
     */
    #[Test]
    public function la_preference_du_compte_sert_quand_la_session_n_a_rien_choisi(): void
    {
        $citoyen = User::factory()->citizen()->create(['locale' => 'fr']);

        $this->actingAs($citoyen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mon espace');
    }

    /**
     * BUT A CHOICE MADE IN THIS SESSION OUTRANKS IT (D-077).
     *
     * The precedence used to run the other way, and it broke the ordinary
     * case: a visitor switches the sign-in page to French, signs in, and the
     * service answers in English because the account was left on English.
     * An action taken seconds ago outranks a preference saved weeks ago.
     */
    #[Test]
    public function un_choix_fait_dans_la_session_l_emporte_sur_le_compte(): void
    {
        $officier = User::factory()->officer(
            CivilStatusCenter::factory()->create()
        )->create(['locale' => 'en']);

        $this->withSession(['locale' => 'fr'])
            ->actingAs($officier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Poste de vérification', false);
    }

    /**
     * And the session choice does NOT overwrite the account.
     *
     * Someone switching for one visit should not silently change what their
     * other devices, and their notification e-mails, will use.
     */
    #[Test]
    public function un_choix_de_session_ne_reecrit_pas_la_preference_du_compte(): void
    {
        $citoyen = User::factory()->citizen()->create(['locale' => 'en']);

        $this->withSession(['locale' => 'fr'])
            ->actingAs($citoyen)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame('en', $citoyen->refresh()->locale);
    }

    /** Switching while signed in writes the choice to the account. */
    #[Test]
    public function changer_de_langue_connecte_enregistre_le_choix_sur_le_compte(): void
    {
        $citoyen = User::factory()->citizen()->create(['locale' => null]);

        $this->actingAs($citoyen)->post(route('locale.update'), ['locale' => 'fr']);

        $this->assertSame('fr', $citoyen->refresh()->locale);
    }

    /**
     * The browser's language is honoured on a first visit.
     *
     * Someone arriving from a French-configured phone should not have to find
     * a picker before they can read the page.
     */
    #[Test]
    public function la_langue_du_navigateur_sert_a_la_premiere_visite(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-CM,fr;q=0.9,en;q=0.8'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="fr"', false);
    }

    /**
     * AND NO SCREEN PRINTS A RAW KEY.
     *
     * A missing translation does not raise an error: Laravel prints the key.
     * This walks the public screens in both languages and refuses anything
     * that looks like `group.some_key` in the rendered text.
     */
    #[Test]
    public function aucun_ecran_public_n_affiche_une_cle_de_traduction(): void
    {
        foreach (array_keys(Locales::SUPPORTED) as $locale) {
            foreach (['home', 'login', 'register', 'password.request', 'health'] as $route) {
                $html = (string) $this->withSession(['locale' => $locale])
                    ->get(route($route))
                    ->assertOk()
                    ->getContent();

                $texte = strip_tags($html);

                // Une cle non traduite se lit « groupe.sous.cle » dans le texte.
                preg_match_all('/\b[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)*\b/', $texte, $suspects);

                $cles = array_values(array_filter(
                    array_unique($suspects[0]),
                    static fn (string $c): bool => str_starts_with($c, 'common.')
                        || str_starts_with($c, 'auth.')
                        || str_starts_with($c, 'home.')
                        || str_starts_with($c, 'admin.')
                        || str_starts_with($c, 'errors.'),
                ));

                $this->assertSame([], $cles, "Untranslated keys on {$route} in {$locale}: ".implode(', ', $cles));
            }
        }
    }
}
