<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\CivilStatusCenter;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les ecrans d'authentification refondus (D-080).
 *
 * CE QUE CES TESTS FERMENT. La maquette fournie proposait de se connecter avec
 * un numero de telephone, annoncait une politique de mot de passe plus faible
 * que celle qui est appliquee, promettait des notifications par SMS, exigeait
 * un sexe dont l'application n'a aucun usage, et offrait un suivi public.
 *
 * Le plus insidieux est la politique de mot de passe : la maquette affichait
 * « 8 caracteres minimum, une lettre, un chiffre » avec des coches vertes en
 * direct. Quelqu'un les aurait toutes vues vertes, puis son inscription aurait
 * ete refusee sans qu'il comprenne pourquoi.
 */
class AuthScreensTest extends TestCase
{
    /**
     * LES REGLES AFFICHEES SONT CELLES QUI SONT APPLIQUEES.
     *
     * La longueur minimale est lue depuis la politique elle-meme : si elle
     * change et que l'ecran ne suit pas, ce test tombe.
     */
    #[Test]
    public function l_inscription_annonce_la_politique_reellement_appliquee(): void
    {
        $html = strip_tags((string) $this->get(route('register'))->assertOk()->getContent());

        $this->assertStringContainsString('12', $html,
            "L'inscription n'annonce pas la longueur minimale reelle du mot de passe.");

        // La maquette annoncait huit caracteres : la moitie de la politique.
        $this->assertStringNotContainsString('8 characters', $html);
        $this->assertStringNotContainsString('8 caractères', $html);
    }

    /** Un mot de passe conforme a la maquette est bien refuse par le serveur. */
    #[Test]
    public function un_mot_de_passe_conforme_a_la_maquette_est_refuse(): void
    {
        $this->post(route('register'), [
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'email' => 'test-maquette@example.test',
            // Huit caracteres, une lettre, un chiffre : la regle de la maquette.
            'password' => 'abcdefg1',
            'password_confirmation' => 'abcdefg1',
            'accepts_terms' => '1',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test-maquette@example.test']);
    }

    /**
     * L'IDENTIFIANT EST UNE ADRESSE, ET RIEN D'AUTRE.
     *
     * Offrir le telephone ferait echouer la connexion de quiconque le
     * saisirait : Fortify est configure sur `email` et rien ne resout un
     * numero vers un compte.
     */
    #[Test]
    public function la_connexion_ne_demande_pas_de_telephone(): void
    {
        $html = (string) $this->get(route('login'))->assertOk()->getContent();

        $this->assertSame('email', config('fortify.username'));
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="tel"/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="phone"/i', $html);
    }

    /** Ni telephone ni sexe a l'inscription : aucun des deux n'a d'usage. */
    #[Test]
    public function l_inscription_ne_collecte_pas_de_donnee_sans_usage(): void
    {
        $html = (string) $this->get(route('register'))->assertOk()->getContent();

        foreach (['phone', 'gender', 'sexe'] as $champ) {
            $this->assertDoesNotMatchRegularExpression(
                '/<(input|select)[^>]*name="'.$champ.'"/i',
                $html,
                "L'inscription collecte « {$champ} », dont l'application ne fait rien."
            );
        }
    }

    /** Aucune promesse de SMS sur les deux ecrans, dans les deux langues. */
    #[Test]
    public function les_ecrans_d_entree_ne_promettent_pas_de_sms(): void
    {
        foreach (['en', 'fr'] as $langue) {
            foreach ([route('login'), route('register')] as $url) {
                $texte = strip_tags((string) $this->withSession(['locale' => $langue])
                    ->get($url)->assertOk()->getContent());

                $this->assertStringNotContainsStringIgnoringCase('SMS', $texte,
                    "SMS promis sur {$url} en {$langue}.");
            }
        }
    }

    /** Aucun suivi public, et une seule porte d'entree pour les quatre roles. */
    #[Test]
    public function la_connexion_n_offre_ni_suivi_public_ni_porte_separee(): void
    {
        $html = (string) $this->get(route('login'))->assertOk()->getContent();

        // Seuls l'adresse, le mot de passe et « rester connecte » sont demandes.
        preg_match_all('/<input[^>]*name="([^"]+)"/i', $html, $champs);

        $this->assertSame(
            ['_token', 'email', 'password', 'remember'],
            array_values(array_unique($champs[1])),
            'La page de connexion porte un champ de plus : la maquette y mettait un numero de suivi.'
        );
    }

    /**
     * LE FILTRE DE LA FILE N'OFFRE PAS D'AUTRE CENTRE.
     *
     * La maquette du poste de l'officier proposait « Centre : Tous ». La
     * portee globale restreint un officier aux demandes de SON centre : un tel
     * filtre laisserait croire le contraire, et chaque choix ne changerait
     * rien.
     */
    #[Test]
    public function la_file_n_offre_pas_de_filtre_par_centre(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($centre)->create();

        $html = (string) $this->actingAs($officier)->get(route('officer.queue'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]*name="(centre|center|civil_status_center_id)"/i',
            $html,
            "La file propose un filtre par centre, alors qu'un officier ne voit que le sien."
        );
    }
}
