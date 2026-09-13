<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Aucun lien ne pointe vers une route qui ne repond pas en GET.
 *
 * CE QUE CE TEST A ATTRAPE (D-071), et qu'aucun autre ne voyait. Les deux
 * boutons « Faire une demande » de l'ecran « Mes demandes » etaient des
 * `<a href>` — donc des GET — vers `citizen.requests.start`, une route en
 * POST. Ils rendaient **404**. Le citoyen ne pouvait pas commencer une
 * demande : la premiere action du service etait inaccessible.
 *
 * POURQUOI LA SUITE NE LE VOYAIT PAS. Les tests envoient un POST directement
 * a la route ; ils ne cliquent jamais le lien. Le test de couverture du
 * diagramme, lui, verifie que la route EXISTE — pas qu'un utilisateur puisse
 * l'atteindre avec le bon verbe. Entre « la route existe » et « on peut
 * l'atteindre », il y avait la place pour un service inutilisable.
 *
 * Ce test lit les gabarits, pas le rendu : c'est volontaire. Un defaut de
 * cette nature se voit dans le balisage, et le chercher la evite d'avoir a
 * ouvrir chaque ecran dans chaque etat.
 */
class LinkVerbsTest extends TestCase
{
    #[Test]
    public function aucun_lien_ne_vise_une_route_sans_verbe_get(): void
    {
        $verbes = [];

        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();

            if ($nom !== null) {
                $verbes[$nom] = array_merge($verbes[$nom] ?? [], $route->methods());
            }
        }

        $fautes = [];

        foreach ($this->gabarits() as $chemin => $contenu) {
            // `<x-button href="{{ route('...') }}">` rend un <a>, donc un GET.
            // `<a href="{{ route('...') }}">` aussi.
            preg_match_all(
                '/<(?:a|x-button)\s[^>]*href=(?:"|\')?\{\{\s*route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/',
                $contenu,
                $trouves
            );

            foreach ($trouves[1] as $nom) {
                if (! isset($verbes[$nom])) {
                    // Une route inconnue est un autre defaut, signale ailleurs.
                    continue;
                }

                if (! in_array('GET', $verbes[$nom], true)) {
                    $fautes[] = sprintf(
                        '%s : lien vers « %s », qui ne répond qu’en %s',
                        $chemin,
                        $nom,
                        implode('/', array_diff($verbes[$nom], ['HEAD']))
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $fautes,
            "Ces liens produiront un 404 : un <a> émet un GET.\n".implode("\n", $fautes)
        );
    }

    /** @return array<string, string> */
    private function gabarits(): array
    {
        $gabarits = [];

        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if (! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $gabarits[str_replace(resource_path('views').'/', '', $fichier->getPathname())]
                = (string) file_get_contents($fichier->getPathname());
        }

        return $gabarits;
    }
}
