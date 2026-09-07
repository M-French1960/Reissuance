<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Regles d'accessibilite verifiables sur le code source.
 *
 * L'audit reel se fait au navigateur, avec axe-core, sur les 17 ecrans : il
 * est refait a la main et consigne dans docs/ACCESSIBILITE.md. Ces tests-ci ne
 * le remplacent pas ; ils empechent la reapparition de ce qu'il a trouve.
 */
class AccessibilityTest extends TestCase
{
    /** @return list<string> */
    private function vues(): array
    {
        $fichiers = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $fichiers[] = $fichier->getPathname();
            }
        }

        sort($fichiers);

        return $fichiers;
    }

    /**
     * Une zone qui defile doit etre atteignable au clavier.
     *
     * `.table-wrap` porte `overflow-x: auto`. Sans `tabindex`, un utilisateur
     * au clavier ne peut pas faire defiler un tableau plus large que l'ecran :
     * son contenu lui est inaccessible. axe-core le signalait en « serious »
     * sur deux ecrans au jalon 6 ; la regle est WCAG 2.1.1.
     */
    #[Test]
    public function toute_zone_defilante_est_atteignable_au_clavier(): void
    {
        $fautives = [];

        foreach ($this->vues() as $vue) {
            foreach (file($vue) as $numero => $ligne) {
                if (! str_contains($ligne, 'class="table-wrap"')) {
                    continue;
                }

                if (! str_contains($ligne, 'tabindex')) {
                    $fautives[] = basename($vue).':'.($numero + 1);
                }
            }
        }

        $this->assertSame([], $fautives, implode("\n", array_merge(
            ['Ces zones defilantes ne sont pas atteignables au clavier :'],
            $fautives,
            ['Ajoutez tabindex="0" et un nom accessible (role="group" + aria-label).'],
        )));
    }

    /**
     * Une zone focalisable nommee : sans nom, un lecteur d'ecran annonce un
     * arret de tabulation sans dire sur quoi.
     */
    #[Test]
    public function toute_zone_defilante_porte_un_nom_accessible(): void
    {
        $fautives = [];

        foreach ($this->vues() as $vue) {
            foreach (file($vue) as $numero => $ligne) {
                if (! str_contains($ligne, 'class="table-wrap"')) {
                    continue;
                }

                if (! str_contains($ligne, 'aria-label')) {
                    $fautives[] = basename($vue).':'.($numero + 1);
                }
            }
        }

        $this->assertSame([], $fautives, 'Zones defilantes sans nom accessible : '.implode(', ', $fautives));
    }

    /**
     * La cible tactile que le projet s'est donnee.
     *
     * Le minimum de la WCAG 2.2 AA est 24 px ; le projet vise 44 px, mesures
     * au navigateur. Ici on verifie seulement que le jeton existe et vaut bien
     * 44 px : la mesure reelle est faite a la main sur les 17 ecrans.
     */
    #[Test]
    public function le_jeton_de_cible_tactile_vaut_bien_44_pixels(): void
    {
        $tokens = file_get_contents(public_path('css/tokens.css'));

        $this->assertMatchesRegularExpression(
            '/--tap-target:\s*(44px|2\.75rem)/',
            $tokens,
            'Le jeton --tap-target doit valoir 44px.'
        );
    }

    /**
     * Chaque page declare sa langue : sans quoi une synthese vocale lit du
     * francais avec une prononciation anglaise.
     */
    #[Test]
    public function la_langue_du_document_est_declaree(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('<html lang=', $layout);
    }

    /** Le lien d'evitement, premier arret de tabulation de chaque page. */
    #[Test]
    public function le_lien_d_evitement_pointe_sur_une_cible_existante(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('class="skip-link" href="#contenu"', $layout);
        $this->assertStringContainsString('id="contenu"', $layout);
        $this->assertStringContainsString('tabindex="-1"', $layout);
    }
}
