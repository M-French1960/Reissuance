<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

        $this->assertTrue($labels->contains('State machine trigger'));
        $this->assertTrue($labels->contains('Audit log is append only'));

        foreach ($response->json('checks') as $check) {
            $this->assertTrue($check['ok'], "Check failing: {$check['label']}, {$check['detail']}");
        }
    }

    /**
     * Un visiteur anonyme obtient le verdict, jamais la carte du systeme.
     *
     * Le detail renseignait sur la version du serveur de base, le nom du
     * compte applicatif, l'hote — le message brut d'une exception PDO y
     * passait tel quel — et sur l'etat des droits du journal d'audit (D-058).
     */
    /**
     * UNE FILE QUI NE DEFILE PLUS EST UNE PANNE SILENCIEUSE (D-089).
     *
     * Toutes les notifications du service sont mises en file (D-006). Si le
     * worker s'arrete, AUCUNE ne part : les ecrans fonctionnent, les decisions
     * s'enregistrent, et plus personne n'est prevenu de rien — ni le demandeur
     * de l'avancement de son dossier, ni le maire qu'un acte attend sa
     * signature. Rien ne le disait avant ce controle.
     */
    #[Test]
    public function la_page_de_sante_signale_une_file_de_notifications_bloquee(): void
    {
        $admin = User::factory()->admin()->create();

        // Un travail en attente depuis une heure : la file ne defile plus.
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->getTimestamp(),
            'created_at' => now()->subHour()->getTimestamp(),
        ]);

        $reponse = $this->actingAs($admin)->getJson('/sante');

        $reponse->assertStatus(503)->assertJsonPath('status', 'degraded');

        $file = collect($reponse->json('checks'))
            ->firstWhere('label', __('admin.health.checks.queue'));

        $this->assertNotNull($file, 'La page de santé doit surveiller la file.');
        $this->assertFalse($file['ok'], 'Une file bloquée doit être signalée.');
    }

    /**
     * Un travail RECENT n'est pas une panne.
     *
     * Sans cette nuance, le moindre pic d'activite declencherait une alerte, et
     * l'alerte finirait par etre ignoree le jour ou elle compte.
     */
    #[Test]
    public function un_travail_recent_en_file_n_est_pas_une_alerte(): void
    {
        $admin = User::factory()->admin()->create();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $file = collect($this->actingAs($admin)->getJson('/sante')->json('checks'))
            ->firstWhere('label', __('admin.health.checks.queue'));

        $this->assertTrue($file['ok'], 'Une file qui vient de recevoir un travail est saine.');
    }

    /** Un travail en echec est une notification que personne n'a recue. */
    #[Test]
    public function la_page_de_sante_signale_les_notifications_en_echec(): void
    {
        $admin = User::factory()->admin()->create();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        $file = collect($this->actingAs($admin)->getJson('/sante')->json('checks'))
            ->firstWhere('label', __('admin.health.checks.queue'));

        $this->assertFalse($file['ok'], 'Un travail en échec doit être signalé.');
    }

    #[Test]
    public function la_page_de_sante_ne_renseigne_pas_un_visiteur_anonyme(): void
    {
        $json = $this->getJson('/sante')->assertOk();

        $json->assertJsonPath('status', 'ok');
        $json->assertJsonMissingPath('checks');

        $html = $this->get('/sante')->assertOk();

        $html->assertSee('Service operational');
        $html->assertDontSee('Audit log is append only');
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
        $this->get('/dev/ui')->assertOk()->assertSee('Component gallery');
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

        // The platform default. A visitor who switches language gets that
        // language's tag, which the locale test covers.
        $this->assertStringContainsString('<html lang="en"', $html);
        $this->assertStringContainsString('name="viewport"', $html);
    }
}
