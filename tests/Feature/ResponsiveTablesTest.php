<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Le contrat dont depend l'affichage des tableaux en cartes (D-083).
 *
 * CE QUE CECI FERME. Sous 720 px, chaque ligne de tableau devient une carte et
 * chaque cellule affiche l'intitule de sa colonne, lu dans `data-label`. Une
 * cellule qui n'en porte pas se retrouve donc SANS intitule : sur un
 * telephone, on lit « Yaoundé I » sans savoir si c'est le centre, la commune
 * ou le lieu de naissance.
 *
 * Aucun test PHP ne peut mesurer une mise en page. Celui-ci verifie le contrat
 * dont elle depend, et il tombe des qu'une colonne est ajoutee sans son
 * intitule — c'est-a-dire au moment ou la faute est commise, pas six mois plus
 * tard sur un telephone.
 *
 * SEULE EXCEPTION : la derniere cellule d'une ligne, celle qui porte les
 * actions. Son intitule serait « Action » repete sur chaque carte, au-dessus
 * d'un bouton qui dit deja ce qu'il fait.
 */
class ResponsiveTablesTest extends TestCase
{
    #[Test]
    public function chaque_cellule_de_tableau_porte_l_intitule_de_sa_colonne(): void
    {
        $fautives = [];

        foreach ($this->vuesAvecTableau() as $chemin => $contenu) {
            foreach ($this->lignes($contenu) as $ligne) {
                // Les cellules de la ligne, dans l'ordre.
                preg_match_all('/<td\b[^>]*>/i', $ligne, $cellules);

                foreach ($cellules[0] as $index => $ouverture) {
                    $derniere = $index === count($cellules[0]) - 1;

                    if ($derniere || str_contains($ouverture, 'data-label')) {
                        continue;
                    }

                    $fautives[] = str_replace(resource_path('views').'/', '', $chemin)
                        .' : cellule '.($index + 1).' '.trim($ouverture);
                }
            }
        }

        $this->assertSame([], $fautives, implode("\n", array_merge(
            ['Cellules sans data-label : en carte, sur telephone, elles perdent leur intitule.'],
            $fautives,
        )));
    }

    /**
     * Les gabarits PDF restent en colonnes, et c'est voulu.
     *
     * Ils sont composes a largeur fixe par dompdf. Les passer en cartes
     * casserait la mise en page de l'acte, qui n'est jamais lu sur un
     * telephone : il est lu imprime, ou dans une visionneuse.
     */
    #[Test]
    public function les_gabarits_de_documents_ne_sont_pas_convertis(): void
    {
        foreach ($this->fichiers(resource_path('views/documents')) as $chemin) {
            $this->assertStringNotContainsString('table-wrap', (string) file_get_contents($chemin),
                'Un gabarit de document passerait en cartes : '.basename($chemin));
        }
    }

    /** @return array<string, string> */
    private function vuesAvecTableau(): array
    {
        $vues = [];

        foreach ($this->fichiers(resource_path('views')) as $chemin) {
            $contenu = (string) file_get_contents($chemin);

            // Seuls les tableaux d'ecran passent en cartes : ils vivent tous
            // dans un `.table-wrap`, et les gabarits PDF n'en ont aucun.
            if (str_contains($contenu, 'table-wrap') && ! str_contains($chemin, '/dev/')) {
                $vues[$chemin] = $contenu;
            }
        }

        $this->assertNotEmpty($vues, 'Aucune vue avec tableau trouvee.');

        return $vues;
    }

    /** @return list<string> */
    private function lignes(string $contenu): array
    {
        $contenu = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contenu);

        preg_match_all('/<tbody>(.*?)<\/tbody>/is', $contenu, $corps);

        $lignes = [];

        foreach ($corps[1] as $bloc) {
            preg_match_all('/<tr\b.*?<\/tr>/is', $bloc, $trouvees);
            $lignes = array_merge($lignes, $trouvees[0]);
        }

        return $lignes;
    }

    /** @return list<string> */
    private function fichiers(string $racine): array
    {
        $trouves = [];

        if (! is_dir($racine)) {
            return $trouves;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $trouves[] = $fichier->getPathname();
            }
        }

        sort($trouves);

        return $trouves;
    }
}
