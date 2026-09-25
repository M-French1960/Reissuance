<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La page d'accueil livree par le client, et ce qu'elle n'a pas le droit de
 * promettre (D-078).
 *
 * CE QUE CES TESTS FERMENT. La maquette fournie etait belle et fausse. Elle
 * annoncait une notification par SMS que l'application n'envoie pas, une
 * declaration de perte que le formulaire ne demande pas, des actes de mariage
 * et de deces « bientot » dont personne n'a fixe la date, un suivi public qui
 * n'existe pas — et laissait deux reponses de FAQ en « [A completer] », sous
 * les yeux du citoyen.
 *
 * Elle portait aussi ses styles et son script EN LIGNE, et ses polices chez un
 * tiers : la politique de securite du projet aurait tout refuse, et la page se
 * serait affichee nue. Aucun test ne l'aurait vu : elle rend 200 dans les deux
 * cas.
 */
class HomeLandingTest extends TestCase
{
    private function accueil(string $langue = 'en'): string
    {
        return (string) $this->withSession(['locale' => $langue])
            ->get(route('home'))
            ->assertOk()
            ->getContent();
    }

    /**
     * RIEN EN LIGNE, RIEN CHEZ UN TIERS.
     *
     * C'est la regle que la maquette violait trois fois. Un <style> en ligne,
     * un <script> en ligne et un lien vers des polices distantes : la CSP
     * `style-src 'self'; script-src 'self'; font-src 'self'` les refuse tous
     * les trois, silencieusement, et la page perd sa mise en forme.
     */
    #[Test]
    public function l_accueil_ne_porte_ni_style_ni_script_en_ligne(): void
    {
        $html = $this->accueil();

        $this->assertDoesNotMatchRegularExpression(
            '/<style[\s>]/i',
            $html,
            "L'accueil porte un <style> en ligne : la CSP le refusera et la page s'affichera nue."
        );

        // Un <script src=...> est admis ; un <script> avec du contenu, non.
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $scripts);

        foreach ($scripts[1] as $corps) {
            $this->assertSame(
                '',
                trim($corps),
                "L'accueil porte du JavaScript en ligne : la CSP le refusera."
            );
        }
    }

    /** Aucune ressource ne vient d'une autre origine. */
    #[Test]
    public function l_accueil_ne_charge_rien_depuis_un_tiers(): void
    {
        $html = $this->accueil();

        preg_match_all('/(?:src|href)="(https?:)?\/\/([^"\/]+)/i', $html, $origines);

        $etrangeres = array_values(array_unique(array_filter(
            $origines[2],
            static fn (string $hote): bool => ! str_contains($hote, 'localhost')
                && ! str_contains($hote, '127.0.0.1'),
        )));

        $this->assertSame(
            [],
            $etrangeres,
            'Ressources tierces sur la premiere page du service : '.implode(', ', $etrangeres)
                .". Elles feraient fuir l'adresse IP de chaque visiteur, et la CSP les refuserait."
        );
    }

    /**
     * AUCUN TEXTE DE REMPLISSAGE NE PARVIENT AU CITOYEN.
     *
     * La maquette livrait deux reponses de FAQ en « [A completer : ...] ».
     * Une administration ne publie pas ses notes de travail.
     */
    #[Test]
    public function l_accueil_ne_contient_aucun_texte_a_completer(): void
    {
        foreach (['en', 'fr'] as $langue) {
            $html = $this->accueil($langue);

            foreach (['À compléter', 'A completer', 'to be completed', 'Lorem ipsum', 'TODO', 'FIXME'] as $marqueur) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $marqueur,
                    $html,
                    "L'accueil affiche « {$marqueur} » en {$langue}."
                );
            }
        }
    }

    /**
     * ELLE NE PROMET PAS DE SMS : L'APPLICATION N'EN ENVOIE AUCUN.
     *
     * Les canaux de RequestStatusChanged sont `database` et `mail`. Promettre
     * un SMS ferait attendre un message qui ne viendrait jamais, a quelqu'un
     * qui n'ouvre peut-etre pas sa boite aux lettres electronique.
     */
    #[Test]
    public function l_accueil_ne_promet_pas_de_notification_par_sms(): void
    {
        foreach (['en', 'fr'] as $langue) {
            $html = strip_tags($this->accueil($langue));

            // Le mot peut apparaitre pour DIRE qu'il n'y en a pas ; ce qui est
            // interdit, c'est de l'annoncer comme un canal disponible.
            foreach (['par SMS et', 'by SMS and', 'SMS et e-mail', 'SMS and email'] as $promesse) {
                $this->assertStringNotContainsStringIgnoringCase($promesse, $html,
                    "L'accueil promet une notification par SMS en {$langue}.");
            }
        }
    }

    /**
     * AUCUN FORMULAIRE DE SUIVI PUBLIC.
     *
     * Une reference est devinable. Un suivi public dirait a qui la saisit
     * l'etat du dossier d'un inconnu.
     */
    #[Test]
    public function l_accueil_n_offre_pas_de_suivi_public(): void
    {
        $html = $this->accueil();

        $this->assertDoesNotMatchRegularExpression(
            '/<input\b(?![^>]*type="hidden")/i',
            $html,
            "L'accueil porte un champ de saisie : le suivi d'un dossier passe par la connexion."
        );
    }

    /** Toute ancre de la page pointe sur une section qui existe. */
    #[Test]
    public function toutes_les_ancres_de_la_page_existent(): void
    {
        foreach (['en', 'fr'] as $langue) {
            $html = $this->accueil($langue);

            preg_match_all('/href="#([^"]+)"/', $html, $ancres);
            preg_match_all('/\bid="([^"]+)"/', $html, $identifiants);

            $manquantes = array_values(array_diff(
                array_unique($ancres[1]),
                $identifiants[1],
            ));

            $this->assertSame([], $manquantes,
                "Ancres sans cible en {$langue} : ".implode(', ', $manquantes));
        }
    }

    /**
     * LE BANDEAU DE DEMONSTRATION PASSE AVANT LE RESTE.
     *
     * Il doit se lire avant le premier appel a l'action : quelqu'un qui clique
     * « Commencer une demande » sans l'avoir lu engage une demarche pour un
     * document qui ne vaut rien.
     */
    #[Test]
    public function le_bandeau_de_demonstration_precede_le_premier_appel_a_l_action(): void
    {
        config(['phoenix.providers.signature' => 'fake']);

        $html = $this->accueil();

        $bandeau = strpos($html, 'home-banner');
        $heros = strpos($html, 'home-hero__cta');

        $this->assertNotFalse($bandeau, "L'accueil ne porte pas le bandeau de demonstration.");
        $this->assertLessThan($heros, $bandeau,
            'Le bandeau de demonstration passe apres le premier bouton : il serait lu trop tard.');
    }

    /**
     * LE MENU DU TELEPHONE RESTE OUVRABLE SANS JAVASCRIPT.
     *
     * Constate dans un navigateur script desactive, pas deduit : la liste
     * etait `display: none` par defaut et seul le bouton pouvait la rouvrir.
     * Sans JavaScript, les quatre liens de section etaient donc perdus. Le
     * repli appartient maintenant au script : toute regle qui cache la liste
     * doit etre gardee par l'attribut que le script pose.
     */
    #[Test]
    public function le_repli_du_menu_est_reserve_au_script(): void
    {
        $css = (string) file_get_contents(public_path('css/home.css'));

        preg_match_all('/([^{}]*\.home-nav__links[^{}]*)\{([^}]*)\}/', $css, $regles, PREG_SET_ORDER);

        foreach ($regles as $regle) {
            if (! preg_match('/display:\s*none/i', $regle[2])) {
                continue;
            }

            $this->assertStringContainsString(
                '[data-js]',
                $regle[1],
                "Une regle cache le menu sans attendre le script : « {$regle[1]} ». "
                    .'Sans JavaScript, les liens de section deviennent inatteignables sur telephone.'
            );
        }
    }
}
