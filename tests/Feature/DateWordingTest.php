<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * A date format string carries no words (D-077).
 *
 * THE DEFECT THIS GUARDS. A Carbon format string is not translated: only the
 * month and day names inside it are. A literal preposition written into the
 * pattern therefore survives every language. The citizen's timeline carried
 * `'d F Y à H:i'`, so the English screen read:
 *
 *     16 September 2026 à 01:03
 *
 * The date translated. The word between the date and the time did not. Found
 * by putting the French and the English screen side by side, not by a test.
 *
 * Words that join a date to a time belong in the language files, where
 * `common.date_and_time` holds them.
 */
class DateWordingTest extends TestCase
{
    /**
     * Letters a date pattern may legitimately contain.
     *
     * Carbon's own format characters, plus the separators and the escape
     * marker. Anything else is a word someone typed.
     */
    private const FORMAT_CHARS = 'dDjlNSwzWFmMntLoXxYyaABgGhHisuvetTIOPpZcrU \\/:.,-_';

    /** @return list<string> */
    private function sources(): array
    {
        $trouves = [];

        foreach ([app_path(), resource_path('views')] as $racine) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $fichier) {
                if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.php')) {
                    $trouves[] = $fichier->getPathname();
                }
            }
        }

        sort($trouves);

        return $trouves;
    }

    /** No format string smuggles a word past the translator. */
    #[Test]
    public function aucun_format_de_date_ne_contient_de_mot(): void
    {
        $fautifs = [];

        foreach ($this->sources() as $fichier) {
            $source = (string) file_get_contents($fichier);

            preg_match_all(
                "/(?:translatedFormat|isoFormat|->format)\(\s*'([^']*)'/",
                $source,
                $trouves,
            );

            foreach ($trouves[1] as $format) {
                $restant = str_replace(str_split(self::FORMAT_CHARS), '', $format);

                if ($restant !== '') {
                    $fautifs[] = str_replace(base_path().'/', '', $fichier).": '{$format}'";
                }
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['These date formats contain a word, which no language will translate:'],
            $fautifs,
            ["Put the wording in a language file, as 'common.date_and_time' does."],
        )));
    }
}
