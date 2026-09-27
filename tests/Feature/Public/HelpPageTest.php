<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Http\Controllers\HelpController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La page d'aide (D-097).
 *
 * Deux choses comptent ici, et aucune n'est la mise en page : que les
 * questions soient TOUTES lisibles sans JavaScript, et qu'aucun formulaire de
 * contact ne reapparaisse.
 */
class HelpPageTest extends TestCase
{
    #[Test]
    public function la_page_est_lisible_sans_compte(): void
    {
        $this->get(route('help'))->assertOk()->assertSee('Help');
    }

    /**
     * LE FILTRE EST UN ENRICHISSEMENT, JAMAIS UNE CONDITION.
     *
     * Le test HTTP ne joue aucun JavaScript : il voit donc exactement ce que
     * voit un navigateur sans script. Les cinq questions doivent y etre.
     */
    #[Test]
    public function toutes_les_questions_sont_servies_dans_le_html(): void
    {
        $reponse = $this->get(route('help'))->assertOk();

        foreach (HelpController::QUESTIONS as $numero) {
            $reponse->assertSee(__("home.faq_q{$numero}"), false);
            $reponse->assertSee(__("home.faq_a{$numero}"), false);
        }
    }

    /** Le champ de recherche est livre masque : un champ inerte est pire que rien. */
    #[Test]
    public function le_champ_de_recherche_est_masque_tant_que_le_script_n_a_pas_tourne(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertSee('data-help-filter-wrap hidden', false);
    }

    /**
     * AUCUN FORMULAIRE DE CONTACT. La maquette en prevoyait un, sans
     * destinataire, et portait elle-meme « Reponse sous [delai a definir] ».
     * Ce test empeche qu'il revienne par inadvertance.
     */
    #[Test]
    public function la_page_ne_porte_aucun_formulaire(): void
    {
        $html = $this->get(route('help'))->assertOk()->getContent();

        // Le selecteur de langue de l'en-tete est un formulaire legitime ; on
        // cherche donc un formulaire qui poste AILLEURS que vers la langue.
        preg_match_all('/<form[^>]*action="([^"]*)"/i', (string) $html, $trouves);

        foreach ($trouves[1] as $action) {
            $this->assertStringContainsString(
                '/langue',
                $action,
                "Un formulaire poste vers {$action}. La page d'aide n'a aucun destinataire : voir D-097."
            );
        }
    }

    #[Test]
    public function la_page_dit_quels_canaux_existent_vraiment(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertSee('Reaching a person')
            ->assertSee(route('track.show'), false)
            ->assertSee(route('login'), false);
    }

    /**
     * LES QUESTIONS NE SONT PAS RECOPIEES. Elles vivent dans les fichiers de
     * langue de l'accueil, et les deux ecrans les lisent. Une copie aurait
     * diverge a la premiere correction — ce jour est deja arrive : la reponse
     * 4 annoncait une limite que D-087 avait levee.
     */
    #[Test]
    public function l_accueil_et_l_aide_servent_le_meme_texte(): void
    {
        $aide = (string) $this->get(route('help'))->getContent();
        $accueil = (string) $this->get(route('home'))->getContent();

        foreach (HelpController::QUESTIONS as $numero) {
            $question = e(__("home.faq_q{$numero}"));

            $this->assertStringContainsString($question, $aide);
            $this->assertStringContainsString($question, $accueil);
        }
    }

    /** La limite levee par D-087 ne doit pas se relire dans la FAQ. */
    #[Test]
    public function la_faq_n_annonce_plus_une_limite_levee(): void
    {
        foreach (['en', 'fr'] as $langue) {
            $this->assertStringNotContainsString(
                'no longer be replaced',
                (string) __('home.faq_a4', [], $langue),
                "La FAQ [{$langue}] annonce encore une limite que le complement (D-087) a levee."
            );
            $this->assertStringNotContainsString(
                'ne peuvent plus être remplacées',
                (string) __('home.faq_a4', [], $langue),
                "La FAQ [{$langue}] annonce encore une limite que le complement (D-087) a levee."
            );
        }
    }
}
