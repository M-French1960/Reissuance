<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagesTest extends TestCase
{
    #[Test]
    public function la_page_d_accueil_repond(): void
    {
        $this->get('/')->assertOk()->assertSee('PHOENIX');
    }

    #[Test]
    public function la_page_de_sante_verifie_les_barrieres_de_securite(): void
    {
        // Le detail est reserve a l'administration (D-058) : c'est donc en
        // administrateur qu'on verifie que les barrieres sont bien controlees.
        $response = $this->actingAs(User::factory()->admin()->create())->getJson('/sante');

        $response->assertOk()->assertJsonPath('status', 'ok');

        $labels = collect($response->json('checks'))->pluck('label');

        $this->assertTrue($labels->contains('Déclencheur de machine à états'));
        $this->assertTrue($labels->contains("Journal d'audit en ajout seul"));

        foreach ($response->json('checks') as $check) {
            $this->assertTrue($check['ok'], "Vérification en échec : {$check['label']} — {$check['detail']}");
        }
    }

    /**
     * Un visiteur anonyme obtient le verdict, jamais la carte du systeme.
     *
     * Le detail renseignait sur la version du serveur de base, le nom du
     * compte applicatif, l'hote — le message brut d'une exception PDO y
     * passait tel quel — et sur l'etat des droits du journal d'audit (D-058).
     */
    #[Test]
    public function la_page_de_sante_ne_renseigne_pas_un_visiteur_anonyme(): void
    {
        $json = $this->getJson('/sante')->assertOk();

        $json->assertJsonPath('status', 'ok');
        $json->assertJsonMissingPath('checks');

        $html = $this->get('/sante')->assertOk();

        $html->assertSee('Service opérationnel');
        $html->assertDontSee("Journal d'audit en ajout seul");
        $html->assertDontSee('MariaDB');
        $html->assertDontSee('MySQL');
        $html->assertDontSee(config('database.connections.mysql.username'));
    }

    /** Une sonde de supervision garde son code HTTP : 200 ou 503. */
    #[Test]
    public function la_sonde_anonyme_conserve_son_code_http(): void
    {
        $this->getJson('/sante')->assertStatus(200)->assertJsonPath('status', 'ok');
    }

    #[Test]
    public function la_galerie_est_accessible_hors_production(): void
    {
        $this->get('/dev/ui')->assertOk()->assertSee('Galerie de composants');
    }

    /**
     * La galerie expose la structure interne de l'interface : elle n'a rien a
     * faire sur un service en ligne (8.3 du brief).
     */
    #[Test]
    public function la_galerie_n_existe_pas_en_production(): void
    {
        // Hors production, la route existe.
        $this->assertTrue(Route::has('dev.ui'));

        // Et elle est bien conditionnee : on relit la declaration plutot que
        // de basculer l'environnement, ce qui reinitialiserait l'application.
        $this->assertStringContainsString(
            "if (! app()->environment('production'))",
            file_get_contents(base_path('routes/web.php')),
            'La galerie doit être conditionnée à un environnement hors production.'
        );
    }

    #[Test]
    public function les_pages_declarent_la_langue_et_le_viewport(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<html lang="fr"', $html);
        $this->assertStringContainsString('name="viewport"', $html);
    }
}
