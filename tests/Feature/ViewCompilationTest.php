<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Toutes les vues compilent.
 *
 * Ce test existe à cause d'un défaut réel : `@disabled(...)` écrit sur une
 * balise de composant — `<x-button @disabled(...)>` — casse la compilation
 * Blade. Le compilateur de composants ne traite pas les directives dans la
 * liste d'attributs et produit du PHP déséquilibré. Le symptôme est une
 * ParseError sur un « endif » situé des dizaines de lignes plus loin.
 *
 * Deux vues étaient concernées, dont l'écran d'envoi de la demande citoyenne,
 * cassé depuis le jalon 3 : aucun test ne le RENDAIT, ils postaient
 * directement vers la route. Une suite verte ne prouve que ce qu'elle teste.
 */
class ViewCompilationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function toutesLesVues(): iterable
    {
        $racine = dirname(__DIR__, 2).'/resources/views';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fichier) {
            if (! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $relatif = str_replace($racine.'/', '', $fichier->getPathname());

            yield $relatif => [$fichier->getPathname()];
        }
    }

    #[Test]
    #[DataProvider('toutesLesVues')]
    public function chaque_vue_produit_du_php_valide(string $chemin): void
    {
        $compile = app('blade.compiler')->compileString((string) file_get_contents($chemin));

        $temporaire = tempnam(sys_get_temp_dir(), 'phoenix-view').'.php';
        file_put_contents($temporaire, "<?php ?>\n".$compile);

        $sortie = [];
        $code = 0;
        exec('php -l '.escapeshellarg($temporaire).' 2>&1', $sortie, $code);
        @unlink($temporaire);

        $this->assertSame(
            0,
            $code,
            basename($chemin).' ne compile pas : '.implode("\n", $sortie)
        );
    }

    /**
     * `@disabled` et consorts sur une balise de composant : interdit.
     *
     * Le passage par une propriété (`:disabled="…"`) est la seule forme sûre.
     */
    #[Test]
    public function aucune_directive_dans_une_balise_de_composant(): void
    {
        $fautes = [];
        $racine = resource_path('views');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fichier) {
            if (! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $contenu = (string) file_get_contents($fichier->getPathname());

            // Les commentaires Blade sont retirés : celui qui documente ce
            // piège dans components/button.blade.php le cite forcément.
            $contenu = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contenu);

            // Une directive @quelquechose( entre « <x-… » et le « > » fermant.
            if (preg_match_all('/<x-[a-z0-9.-]+[^>]*?@(disabled|checked|selected|readonly|required)\s*\(/is', $contenu, $m)) {
                $fautes[str_replace($racine.'/', '', $fichier->getPathname())] = $m[1];
            }
        }

        $this->assertSame(
            [],
            $fautes,
            'Directives dans une balise de composant — la compilation Blade casse : '.json_encode($fautes)
        );
    }
}
