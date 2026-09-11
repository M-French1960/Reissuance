<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CivilStatusCenter;
use App\Models\User;
use App\Support\Settings\SystemSetting;
use App\Support\Settings\SystemSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ecran de reglages : utile, et incapable de faire fuir un secret.
 *
 * LE RISQUE PROPRE A CET ECRAN. Il affiche la configuration du systeme, et
 * cette configuration contient des secrets : cle d'index aveugle, cles de
 * l'agregateur de paiement, secret de signature des rappels. Un ecran de
 * reglages est le genre d'endroit ou un secret finit par s'afficher « pour
 * deboguer », puis y reste.
 *
 * Ces tests posent donc des valeurs de secret RECONNAISSABLES et verifient
 * qu'aucune ne ressort — ni dans le HTML, ni dans les objets manipules par la
 * vue. La protection ne tient pas a un masquage a l'affichage mais a la
 * construction : `SystemSetting::secret()` ne retient que la presence.
 *
 * Voir D-060.
 */
class SettingsConsoleTest extends TestCase
{
    /** Des valeurs qu'on repere immediatement si elles fuient. */
    private const SECRETS = [
        'phoenix.blind_index_key' => 'SECRET-INDEX-AVEUGLE-NE-DOIT-PAS-FUIR',
        'phoenix.payments.hrskills.public_key' => 'SECRET-CLE-PUBLIQUE-NE-DOIT-PAS-FUIR',
        'phoenix.payments.hrskills.secret_key' => 'SECRET-CLE-SECRETE-NE-DOIT-PAS-FUIR',
        'phoenix.payments.hrskills.webhook_secret' => 'SECRET-RAPPELS-NE-DOIT-PAS-FUIR',
    ];

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function poserLesSecrets(): void
    {
        config(self::SECRETS);
    }

    #[Test]
    public function l_ecran_repond_a_l_administrateur(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Réglages')
            ->assertSee('PHOENIX_PAYMENT_GATE');
    }

    /** LE test de cet écran. */
    #[Test]
    public function aucune_valeur_de_secret_n_apparait_dans_la_page(): void
    {
        $this->poserLesSecrets();

        $reponse = $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk();

        foreach (self::SECRETS as $cle => $valeur) {
            $reponse->assertDontSee($valeur);
            // Meme un fragment ne doit pas passer.
            $reponse->assertDontSee(substr($valeur, 0, 12));
        }

        // Mais la PRESENCE du secret, elle, doit être visible.
        $reponse->assertSee('configuré');
    }

    /**
     * La protection tient a la construction, pas au gabarit.
     *
     * Meme en inspectant les objets que la vue recoit, la valeur n'y est pas :
     * elle n'est jamais entree dedans.
     */
    #[Test]
    public function la_valeur_d_un_secret_n_entre_pas_dans_l_objet(): void
    {
        $reglage = SystemSetting::secret('Essai', 'SECRET-NE-DOIT-PAS-FUIR', 'PHOENIX_ESSAI');

        $serialise = json_encode(get_object_vars($reglage), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('SECRET-NE-DOIT-PAS-FUIR', $serialise);
        $this->assertSame('configuré', $reglage->valeur);
        $this->assertTrue($reglage->sensible);
    }

    #[Test]
    public function un_secret_absent_est_signale_comme_tel(): void
    {
        $reglage = SystemSetting::secret('Essai', '', 'PHOENIX_ESSAI');

        $this->assertSame('non configuré', $reglage->valeur);
        $this->assertNotNull($reglage->alerte);
    }

    /**
     * Le service rendu par cet ecran : dire qu'on tourne sur du factice.
     *
     * Aujourd'hui, rien dans l'application ne permet de voir qu'aucune
     * verification n'est reelle. Une demonstration prise pour un service est
     * le risque d'exploitation le plus concret a ce stade.
     */
    #[Test]
    public function les_adaptateurs_factices_sont_annonces_sans_ambiguite(): void
    {
        config(['phoenix.providers.identity' => 'fake', 'phoenix.providers.registry' => 'fake']);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Ce système ne vérifie rien de réel')
            ->assertSee("Vérification d'identité (DGSN)")
            ->assertSee('aucune valeur');

        $this->assertContains("Vérification d'identité (DGSN)", SystemSettings::fakeProviders());
    }

    #[Test]
    public function un_systeme_entierement_reel_n_affiche_pas_l_alerte(): void
    {
        config(array_combine(
            array_map(static fn (string $c): string => "phoenix.providers.{$c}", array_keys(SystemSettings::PROVIDERS)),
            array_fill(0, count(SystemSettings::PROVIDERS), 'real'),
        ));

        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Ce système ne vérifie rien de réel');
    }

    /** Un encaissement active sans tarif doit sauter aux yeux. */
    #[Test]
    public function un_encaissement_sans_tarif_est_signale(): void
    {
        config(['phoenix.payments.gate' => 'before_submission', 'phoenix.payments.amount_minor' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('refusera de servir');
    }

    #[Test]
    public function aucun_autre_role_n_accede_aux_reglages(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        foreach ([
            User::factory()->officer($centre)->create(),
            User::factory()->mayor($centre->commune)->create(),
            User::factory()->citizen()->create(),
        ] as $acteur) {
            $this->actingAs($acteur)->get(route('admin.settings.index'))->assertForbidden();
        }
    }

    /**
     * L'invite, dans son propre test.
     *
     * `actingAs()` persiste jusqu'a la fin de la methode : verifier l'invite a
     * la suite des roles connectes ne mesurait que le dernier d'entre eux.
     */
    #[Test]
    public function un_visiteur_anonyme_est_renvoye_a_la_connexion(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }

    /** Aucune route d'ecriture ne doit exister sur les reglages. */
    #[Test]
    public function les_reglages_n_ont_aucune_route_d_ecriture(): void
    {
        $ecritures = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.settings.'))
            ->reject(fn ($route): bool => $route->methods() === ['GET', 'HEAD'])
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame(
            [],
            $ecritures,
            "Les réglages sont en lecture seule : basculer un prestataire sur l'adaptateur "
                .'factice depuis un navigateur ferait délivrer des actes sans vérification réelle.'
        );
    }

    #[Test]
    public function la_consultation_est_journalisee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.viewed',
            'actor_id' => $admin->id,
        ]);
    }
}
