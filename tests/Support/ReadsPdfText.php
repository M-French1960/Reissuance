<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Relit un PDF avec pdftotext, un outil INDEPENDANT de notre moteur.
 *
 * POURQUOI CE TRAIT EXISTE, et pourquoi il a fallu le sortir de son test
 * d'origine (D-067). Plusieurs tests cherchaient une chaine dans les OCTETS
 * du PDF. Cela fonctionnait par accident : l'ancien generateur, ecrit a la
 * main, n'ecrivait pas de flux comprime, de sorte que le texte se lisait en
 * clair dans le fichier. dompdf comprime ses flux, et ces tests sont tombes.
 *
 * Ils avaient raison de tomber : chercher une chaine dans des octets ne prouve
 * pas qu'elle est RENDUE. Elle pouvait aussi bien s'y trouver dans un titre de
 * document, une metadonnee ou un fragment jamais dessine. Le seul test qui se
 * relisait deja avec pdftotext, lui, est passe sans broncher.
 */
trait ReadsPdfText
{
    protected function extractText(string $pdf): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'phoenix-pdf').'.pdf';
        file_put_contents($chemin, $pdf);

        $sortie = shell_exec('pdftotext '.escapeshellarg($chemin).' - 2>/dev/null');
        @unlink($chemin);

        if ($sortie === null || trim((string) $sortie) === '') {
            $this->markTestSkipped('pdftotext indisponible : la vérification indépendante du PDF est impossible.');
        }

        return (string) $sortie;
    }

    /**
     * Le nombre de pages, lu par pdfinfo.
     *
     * POURQUOI CE CONTROLE EXISTE (D-067). Le premier rendu par dompdf coupait
     * le bloc de mention finale entre son titre et son paragraphe, et lachait
     * celui-ci sur une deuxieme page presque vide. Aucun test ne l'a vu :
     * le texte extrait etait complet, et c'est tout ce qu'ils regardaient. Il
     * a fallu ouvrir le PDF.
     */
    protected function countPages(string $pdf): int
    {
        $chemin = tempnam(sys_get_temp_dir(), 'phoenix-pdf').'.pdf';
        file_put_contents($chemin, $pdf);

        $sortie = (string) shell_exec('pdfinfo '.escapeshellarg($chemin).' 2>/dev/null');
        @unlink($chemin);

        if (! preg_match('/^Pages:\s+(\d+)/m', $sortie, $trouve)) {
            $this->markTestSkipped('pdfinfo indisponible : le nombre de pages ne peut pas être vérifié.');
        }

        return (int) $trouve[1];
    }
}
