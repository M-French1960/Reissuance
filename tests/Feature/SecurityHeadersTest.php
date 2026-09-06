<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * En-tetes de securite (4.5 du brief).
 *
 * La CSP stricte n'a ete rendue possible que par D-010 : sans Livewire ni
 * Alpine, plus rien n'evalue d'expression a l'execution.
 */
class SecurityHeadersTest extends TestCase
{
    #[Test]
    #[DataProvider('enTetesAttendus')]
    public function les_en_tetes_de_securite_sont_poses(string $header, string $expected): void
    {
        $this->get('/')->assertHeader($header, $expected);
    }

    /** @return iterable<string, array{string, string}> */
    public static function enTetesAttendus(): iterable
    {
        yield 'nosniff' => ['X-Content-Type-Options', 'nosniff'];
        yield 'anti-cadrage' => ['X-Frame-Options', 'DENY'];
        yield 'referent' => ['Referrer-Policy', 'same-origin'];
        yield 'isolation d\'origine' => ['Cross-Origin-Opener-Policy', 'same-origin'];
    }

    #[Test]
    public function la_csp_n_autorise_ni_unsafe_inline_ni_unsafe_eval(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    #[Test]
    public function la_politique_de_permissions_n_ouvre_que_la_camera(): void
    {
        $policy = $this->get('/')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $policy);
        foreach (['microphone=()', 'geolocation=()', 'payment=()', 'usb=()'] as $refuse) {
            $this->assertStringContainsString($refuse, $policy);
        }
    }

    /**
     * HSTS n'a de sens qu'en TLS : l'imposer en developpement local rendrait
     * le site inaccessible en http sur la meme machine.
     */
    #[Test]
    public function hsts_n_est_pas_pose_en_clair(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * La CSP interdit les scripts en ligne : un attribut onclick oublie
     * casserait la page en silence. Le prototype en comptait 12.
     */
    #[Test]
    public function aucune_vue_ne_contient_de_gestionnaire_en_ligne(): void
    {
        $fautes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match_all('/\son(click|change|submit|load|error|input|focus|blur)\s*=/i', $content, $m)) {
                $fautes[$file->getFilename()] = $m[0];
            }
        }

        $this->assertSame([], $fautes, 'Gestionnaires en ligne trouvés : '.json_encode($fautes));
    }

    /**
     * La CSP porte aussi sur les styles : `style-src 'self'` refuse tout
     * attribut style= en ligne. Un style ainsi ecrit n'est pas applique — la
     * page s'affiche mal, EN SILENCE. Constate en exécutant l'application dans
     * un navigateur, alors que les tests etaient au vert.
     */
    #[Test]
    public function aucune_vue_ne_contient_de_style_en_ligne(): void
    {
        $fautes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match_all('/\sstyle\s*=\s*"/i', $content, $m)) {
                $fautes[$file->getFilename()] = count($m[0]);
            }
        }

        $this->assertSame(
            [],
            $fautes,
            'Styles en ligne trouvés — la CSP les refuse : '.json_encode($fautes)
        );
    }

    #[Test]
    public function la_csp_refuse_aussi_les_styles_en_ligne(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("style-src 'self'", $csp);
    }

    #[Test]
    public function les_cookies_de_session_sont_httponly_et_chiffres(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame('lax', config('session.same_site'));
    }

    /** Le formulaire de connexion doit porter un jeton CSRF. */
    #[Test]
    public function les_formulaires_portent_un_jeton_csrf(): void
    {
        $this->get(route('login'))->assertSee('name="_token"', false);
        $this->get(route('register'))->assertSee('name="_token"', false);
    }

    /**
     * Protection CSRF : ce qui est reellement verifiable.
     *
     * On ne peut PAS prouver le rejet a l'execution depuis la suite de tests :
     * PreventRequestForgery::handle() appelle runningUnitTests() et
     * court-circuite entierement le controle des que la suite tourne. Ecrire
     * un test qui « verifie » un 419 donnerait une fausse assurance.
     *
     * Ce qui est verifiable, et qui est ce qui peut reellement casser : que le
     * middleware soit bien pose sur le groupe web, et que les formulaires
     * portent le jeton.
     */
    #[Test]
    public function la_protection_csrf_est_posee_sur_le_groupe_web(): void
    {
        $kernel = app(Kernel::class);
        $groupes = (new \ReflectionClass($kernel))->getProperty('middlewareGroups');
        $web = $groupes->getValue($kernel)['web'] ?? [];

        $protege = collect($web)->contains(
            fn ($m): bool => is_string($m) && str_contains($m, 'PreventRequestForgery')
        );

        $this->assertTrue(
            $protege,
            'Le middleware de protection CSRF doit figurer dans le groupe web : '
            .json_encode(array_map('strval', $web))
        );
    }
}
