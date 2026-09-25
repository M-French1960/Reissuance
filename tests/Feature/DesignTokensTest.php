<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Verifie que le design system tient ses promesses d'accessibilite.
 *
 * L'audit du prototype a mesure 6 couples de contraste en echec AA sur 13, et
 * aucun style de focus dans les 5 feuilles (docs/AUDIT_FRONTEND.md 8.3).
 * Ces defauts ne doivent pas revenir par inadvertance.
 */
class DesignTokensTest extends TestCase
{
    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function token(string $name): string
    {
        $css = file_get_contents(public_path('css/tokens.css'));

        $this->assertMatchesRegularExpression(
            '/--'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/',
            $css,
            "Jeton --{$name} introuvable dans tokens.css."
        );

        preg_match('/--'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/', $css, $m);

        return $m[1];
    }

    /** @return iterable<string, array{string, string}> */
    public static function couplesTexteFond(): iterable
    {
        yield 'texte principal sur surface' => ['color-ink-900', 'color-surface'];
        yield 'texte courant sur surface' => ['color-ink-700', 'color-surface'];
        yield 'texte secondaire sur surface' => ['color-ink-500', 'color-surface'];
        yield 'texte secondaire sur fond creuse' => ['color-ink-500', 'color-surface-sunken'];
        yield 'badge neutre' => ['tone-neutral-fg', 'tone-neutral-bg'];
        yield 'badge en attente' => ['tone-waiting-fg', 'tone-waiting-bg'];
        yield 'badge en cours' => ['tone-progress-fg', 'tone-progress-bg'];
        yield 'badge attention' => ['tone-attention-fg', 'tone-attention-bg'];
        yield 'badge succes' => ['tone-success-fg', 'tone-success-bg'];
        yield 'badge danger' => ['tone-danger-fg', 'tone-danger-bg'];
    }

    #[Test]
    #[DataProvider('couplesTexteFond')]
    public function chaque_couple_texte_fond_atteint_le_niveau_AA(string $fg, string $bg): void
    {
        $ratio = self::ratio($this->token($fg), $this->token($bg));

        $this->assertGreaterThanOrEqual(
            4.5,
            $ratio,
            sprintf('--%s sur --%s : %.2f:1, en dessous du seuil AA de 4,5:1.', $fg, $bg, $ratio)
        );
    }

    #[Test]
    public function le_bouton_primaire_atteint_le_niveau_AA(): void
    {
        $ratio = self::ratio('#ffffff', $this->token('color-brand-700'));

        $this->assertGreaterThanOrEqual(4.5, $ratio);
    }

    #[Test]
    public function un_style_de_focus_visible_est_defini(): void
    {
        $css = file_get_contents(public_path('css/tokens.css'));

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertMatchesRegularExpression('/outline:\s*3px solid/', $css);
    }

    #[Test]
    public function la_cible_tactile_minimale_est_de_44_pixels(): void
    {
        $css = file_get_contents(public_path('css/tokens.css'));

        $this->assertMatchesRegularExpression('/--tap-target:\s*44px/', $css);
    }

    /**
     * Aucune valeur en dur dans une vue (8.3 du brief) : les couleurs
     * viennent toutes de tokens.css.
     */
    #[Test]
    public function aucune_vue_ne_contient_de_couleur_en_dur(): void
    {
        $fautes = [];

        // Parcours recursif : un glob avec ** ne descend que d'un niveau, et
        // l'operateur + sur deux tableaux fait une union par cle, ce qui
        // ecarte silencieusement des fichiers. Les deux pieges ont ete
        // rencontres en ecrivant ce test.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $content, $m)) {
                $fautes[$file->getFilename()] = $m[0];
            }
        }

        $this->assertSame([], $fautes, 'Couleurs en dur trouvées dans des vues : '.json_encode($fautes));
    }

    /**
     * TOUTE VARIABLE CSS UTILISEE EST DEFINIE QUELQUE PART.
     *
     * CE QUE CE TEST FERME (D-080). `app.css` ecrivait
     * `color: var(--color-ink)`. Ce jeton n'existe pas : ils s'appellent
     * --color-ink-900, -700, -500 et -300. Une variable inconnue sans valeur
     * de repli rend la declaration INVALIDE, et la propriete est alors
     * heritee. Ici, la couleur heritee etait le blanc du selecteur pose sur la
     * barre violette : les options du menu deroulant s'affichaient en blanc
     * sur blanc.
     *
     * Rien ne pouvait le voir. Le CSS reste valide au sens du parseur, la page
     * rend 200, et aucune assertion ne lit une liste deroulante ouverte. Une
     * faute de frappe dans un nom de jeton se paie donc a l'ecran, et
     * seulement a l'ecran.
     */
    #[Test]
    public function aucune_variable_css_n_est_utilisee_sans_etre_definie(): void
    {
        $definies = [];
        $utilisees = [];

        foreach (glob(public_path('css/*.css')) as $chemin) {
            $css = (string) file_get_contents($chemin);

            preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $d);
            $definies = array_merge($definies, $d[1]);

            // Un var() avec valeur de repli est sur : il survit a l'absence.
            preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/i', $css, $u);

            foreach ($u[1] as $nom) {
                $utilisees[$nom][] = basename($chemin);
            }
        }

        $definies = array_unique($definies);
        $orphelines = [];

        foreach ($utilisees as $nom => $fichiers) {
            if (! in_array($nom, $definies, true)) {
                $orphelines[] = $nom.' ('.implode(', ', array_unique($fichiers)).')';
            }
        }

        $this->assertSame([], $orphelines,
            'Variables CSS utilisees sans definition ni repli : '.implode(' ; ', $orphelines)
                .'. La declaration est invalide et la propriete est heritee, en silence.');
    }
}
