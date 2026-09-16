<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The interface is typed the way a person types (D-076).
 *
 * WHY THIS FILE EXISTS. The client asked that the screens stop carrying the
 * tells of machine-written text. Two of them are mechanical enough to enforce:
 *
 *   - the EM DASH, "—", used as a mid-sentence pause. A front-end developer
 *     writing an interface reaches for a comma, a colon or a full stop. The
 *     interface was full of them: "Signée — acte disponible", "Transmise au
 *     centre — commune de Yaoundé I", "enregistré le 12/09 — par Officier".
 *
 *   - the NON-BREAKING SPACE, written &nbsp; or dropped in raw as U+00A0,
 *     scattered before colons and question marks. French typography does call
 *     for a thin space there, but it was applied to a handful of strings and
 *     not the rest, which is exactly what makes it read as pasted rather than
 *     typed.
 *
 * WHAT THIS DOES NOT POLICE. Comments in the source, which no visitor reads,
 * and the guillemets « » of French, which are correct French typography and
 * what a francophone developer would write.
 *
 * The rule holds on what reaches a screen: the language files, and the parts
 * of a Blade template outside its {{-- comments --}}.
 */
class TypographyTest extends TestCase
{
    private const EM_DASH = "\u{2014}";

    private const NBSP = "\u{00A0}";

    private const NARROW_NBSP = "\u{202F}";

    /** @return list<string> */
    private function fichiers(string $racine, string $extension): array
    {
        $trouves = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), $extension)) {
                $trouves[] = $fichier->getPathname();
            }
        }

        sort($trouves);

        return $trouves;
    }

    /**
     * Strips the comments: what is left is what reaches a screen.
     *
     * Blade comments, then the PHP and CSS block comments a template can carry
     * inside an @php block or a <style>, then the line comments. A note a
     * developer writes to another developer is not interface text, and the
     * rule has no business reaching into it.
     */
    private function sansCommentaires(string $source): string
    {
        $sans = (string) preg_replace('/\{\{--.*?--\}\}/su', '', $source);
        $sans = (string) preg_replace('#/\*.*?\*/#su', '', $sans);

        return implode("\n", array_filter(
            explode("\n", $sans),
            static fn (string $ligne): bool => ! str_starts_with(ltrim($ligne), '//')
                && ! str_starts_with(ltrim($ligne), '*'),
        ));
    }

    /** No text shown to anyone carries an em dash. */
    #[Test]
    public function aucun_texte_affiche_ne_porte_de_tiret_long(): void
    {
        $fautifs = [];

        foreach ($this->fichiers(lang_path(), '.php') as $fichier) {
            foreach (file($fichier) as $numero => $ligne) {
                $nu = ltrim($ligne);

                if (str_contains($ligne, self::EM_DASH)
                    && ! str_starts_with($nu, '*')
                    && ! str_starts_with($nu, '/*')
                    && ! str_starts_with($nu, '//')) {
                    $fautifs[] = basename(dirname($fichier)).'/'.basename($fichier).':'.($numero + 1);
                }
            }
        }

        foreach ($this->fichiers(resource_path('views'), '.blade.php') as $fichier) {
            if (str_contains($this->sansCommentaires((string) file_get_contents($fichier)), self::EM_DASH)) {
                $fautifs[] = str_replace(resource_path('views').'/', '', $fichier);
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['These texts carry an em dash. Use a comma, a colon or a full stop:'],
            $fautifs,
        )));
    }

    /** Nor a non-breaking space, in any of its spellings. */
    #[Test]
    public function aucun_texte_affiche_ne_porte_d_espace_insecable(): void
    {
        $fautifs = [];

        $sources = array_merge(
            $this->fichiers(lang_path(), '.php'),
            $this->fichiers(resource_path('views'), '.blade.php'),
        );

        foreach ($sources as $fichier) {
            $contenu = str_ends_with($fichier, '.blade.php')
                ? $this->sansCommentaires((string) file_get_contents($fichier))
                : (string) file_get_contents($fichier);

            foreach ([self::NBSP, self::NARROW_NBSP, '&nbsp;', '&#160;', '&#xa0;'] as $marqueur) {
                if (str_contains($contenu, $marqueur)) {
                    $fautifs[] = str_replace([lang_path().'/', resource_path('views').'/'], '', $fichier);
                    break;
                }
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['These texts carry a non-breaking space. Use an ordinary space:'],
            $fautifs,
        )));
    }
}
