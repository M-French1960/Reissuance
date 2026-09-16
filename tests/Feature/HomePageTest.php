<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La page d'accueil s'adresse a un citoyen.
 *
 * CE QUE CES TESTS FERMENT (D-072). L'accueil a longtemps annonce « Socle
 * technique — jalon 1 : l'authentification et les parcours metier arrivent aux
 * jalons suivants », et n'offrait que deux liens d'exploitation — l'etat du
 * service et la galerie de composants. **La premiere page du service disait au
 * citoyen que le service n'existait pas, et ne lui donnait aucun moyen
 * d'entrer.**
 *
 * La suite ne le voyait pas : elle verifiait que la page rend 200. Une page
 * peut rendre 200 et ne servir a personne.
 */
class HomePageTest extends TestCase
{
    #[Test]
    public function l_accueil_offre_au_citoyen_un_moyen_d_entrer(): void
    {
        $reponse = $this->get(route('home'));

        $reponse->assertOk();
        $reponse->assertSee(route('register'));
        $reponse->assertSee(route('login'));
        $reponse->assertSee('Create an account');
    }

    /** Elle dit ce qu'il faut avoir sous la main AVANT de commencer. */
    #[Test]
    public function l_accueil_dit_ce_qu_il_faut_preparer(): void
    {
        $reponse = $this->get(route('home'));

        $reponse->assertSee('identity document', false);
        $reponse->assertSee('photo of yourself', false);
    }

    /**
     * ET ELLE N'ANNONCE PLUS QUE LE SERVICE EST INACHEVE.
     *
     * Le mot « jalon » n'a rien a faire devant un citoyen : c'est un terme de
     * conduite de projet. Ce test tomberait si la banniere revenait.
     */
    #[Test]
    public function l_accueil_ne_parle_pas_de_jalons_ni_de_socle_technique(): void
    {
        $contenu = $this->get(route('home'))->getContent();

        foreach (['jalon', 'Socle technique', 'arrivent aux jalons'] as $terme) {
            $this->assertStringNotContainsString(
                $terme,
                (string) $contenu,
                "L'accueil parle de « {$terme} » : c'est du vocabulaire de projet, pas de service."
            );
        }
    }

    /**
     * LE POINT QUI COMPTE LE PLUS : l'avertissement est donne DES L'ACCUEIL.
     *
     * Tant que le prestataire de signature est l'adaptateur de demonstration,
     * l'acte delivre porte « SANS VALEUR JURIDIQUE ». Laisser un citoyen aller
     * jusqu'au bout du parcours pour recevoir un document inutilisable serait
     * le tromper (D-025, §10 du brief).
     */
    #[Test]
    public function l_accueil_previent_que_les_actes_n_engagent_pas(): void
    {
        config(['phoenix.providers.signature' => 'fake']);

        $reponse = $this->get(route('home'));

        $reponse->assertSee('Demonstration', false);
        $reponse->assertSee('no legal value', false);
    }

    /** Et l'avertissement disparaît le jour où un prestataire réel est branché. */
    #[Test]
    public function l_avertissement_disparait_avec_un_prestataire_reel(): void
    {
        config(['phoenix.providers.signature' => 'real']);

        $reponse = $this->get(route('home'));

        $reponse->assertOk();
        $reponse->assertDontSee('Service de Demonstration', false);
    }

    /**
     * L'en-tete donne un chemin vers la connexion, sur TOUTE page publique.
     *
     * Il ne portait de navigation que pour les personnes connectees : depuis
     * l'etat du service ou une page d'erreur, un visiteur n'avait d'autre
     * recours que la barre d'adresse.
     */
    #[Test]
    public function l_entete_offre_la_connexion_a_un_visiteur_anonyme(): void
    {
        foreach ([route('home'), route('health')] as $url) {
            $reponse = $this->get($url);

            $reponse->assertOk();
            $reponse->assertSee(route('login'));
            $reponse->assertSee('Sign in');
        }
    }

    /** Les liens d'exploitation restent, mais après le parcours du citoyen. */
    #[Test]
    public function les_liens_d_exploitation_viennent_apres(): void
    {
        $contenu = (string) $this->get(route('home'))->getContent();

        $this->assertLessThan(
            strpos($contenu, route('health')),
            strpos($contenu, route('register')),
            "L'état du service passe avant la création de compte : l'accueil s'adresse d'abord au citoyen."
        );
    }
}
