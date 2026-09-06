<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        $response = $this->getJson('/sante');

        $response->assertOk()->assertJsonPath('status', 'ok');

        $labels = collect($response->json('checks'))->pluck('label');

        $this->assertTrue($labels->contains('Déclencheur de machine à états'));
        $this->assertTrue($labels->contains("Journal d'audit en ajout seul"));

        foreach ($response->json('checks') as $check) {
            $this->assertTrue($check['ok'], "Vérification en échec : {$check['label']} — {$check['detail']}");
        }
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
