<?php

declare(strict_types=1);

namespace App\Support\Pdf;

/**
 * Générateur PDF minimal — texte seulement.
 *
 * POURQUOI CE CODE EXISTE : dompdf n'a pas pu être installé depuis
 * l'environnement de construction (échecs réseau répétés, voir D-012 et
 * D-024). Écrire un conteneur PDF simple est un travail borné et vérifiable ;
 * réécrire un moteur de rendu HTML ne le serait pas.
 *
 * CE QUE CE GÉNÉRATEUR FAIT : des pages A4, du texte en Helvetica et
 * Helvetica-Bold, trois tailles, des filets horizontaux, l'encodage WinAnsi
 * (donc les accents français), et l'échappement des caractères réservés.
 *
 * CE QU'IL NE FAIT PAS : images, tableaux, couleurs, retour à la ligne
 * automatique, polices embarquées, compression. Il n'y a aucune raison de
 * l'étendre : dès que le réseau le permet, `composer require dompdf/dompdf`
 * et un gabarit Blade le remplacent avantageusement.
 *
 * La sortie est vérifiée par un test qui relit le PDF avec pdftotext, un
 * outil indépendant : je ne me contente pas de supposer qu'elle est valide.
 */
final class PdfDocument
{
    private const PAGE_WIDTH = 595.28;   // A4 en points

    private const PAGE_HEIGHT = 841.89;

    private const MARGIN = 56.7;         // 20 mm

    /** @var list<string> flux de contenu, une entrée par page */
    private array $pages = [];

    private string $current = '';

    private float $cursor;

    public function __construct()
    {
        $this->cursor = self::PAGE_HEIGHT - self::MARGIN;
    }

    public function text(string $value, float $size = 11, bool $bold = false, float $indent = 0): self
    {
        $this->ensureRoom($size * 1.6);

        $font = $bold ? '/F2' : '/F1';
        $x = self::MARGIN + $indent;

        $this->current .= sprintf(
            "BT %s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $font, $size, $x, $this->cursor, $this->escape($value)
        );

        $this->cursor -= $size * 1.6;

        return $this;
    }

    /** Écrit « Libellé : valeur », le libellé en gras. */
    public function labelled(string $label, string $value, float $size = 11): self
    {
        $this->ensureRoom($size * 1.6);

        $this->current .= sprintf(
            "BT /F2 %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $size, self::MARGIN, $this->cursor, $this->escape($label.' : ')
        );

        // Décalage proportionnel à la longueur du libellé. Helvetica-Bold fait
        // environ 0,55 em de large en moyenne : approximation suffisante ici,
        // et la seule raison pour laquelle ce générateur reste acceptable.
        $offset = mb_strlen($label.' : ') * $size * 0.55;

        $this->current .= sprintf(
            "BT /F1 %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $size, self::MARGIN + $offset, $this->cursor, $this->escape($value)
        );

        $this->cursor -= $size * 1.6;

        return $this;
    }

    public function rule(): self
    {
        $this->ensureRoom(14);
        $this->current .= sprintf(
            "0.6 w 0.75 0.75 0.75 RG %.2f %.2f m %.2f %.2f l S\n",
            self::MARGIN, $this->cursor + 6, self::PAGE_WIDTH - self::MARGIN, $this->cursor + 6
        );
        $this->cursor -= 14;

        return $this;
    }

    public function spacer(float $height = 12): self
    {
        $this->cursor -= $height;

        return $this;
    }

    /**
     * Découpe un paragraphe en lignes.
     *
     * Le PDF n'a aucune notion de retour à la ligne : il faut le faire ici.
     */
    public function paragraph(string $value, float $size = 10, int $charsPerLine = 92): self
    {
        foreach (explode("\n", wordwrap($value, $charsPerLine, "\n", true)) as $line) {
            $this->text($line, $size);
        }

        return $this;
    }

    public function newPage(): self
    {
        $this->pages[] = $this->current;
        $this->current = '';
        $this->cursor = self::PAGE_HEIGHT - self::MARGIN;

        return $this;
    }

    public function render(): string
    {
        $pages = $this->pages;

        if ($this->current !== '') {
            $pages[] = $this->current;
        }

        if ($pages === []) {
            $pages[] = '';
        }

        $objects = [];
        $pageCount = count($pages);

        // 1 = catalogue, 2 = arbre de pages, 3 et 4 = polices,
        // puis un couple (page, contenu) par page.
        $firstPageObject = 5;
        $kids = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObject + $i * 2).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', $kids), $pageCount
        );
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $index => $content) {
            $pageObj = $firstPageObject + $index * 2;
            $contentObj = $pageObj + 1;

            $objects[$pageObj] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH, self::PAGE_HEIGHT, $contentObj
            );

            $objects[$contentObj] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($content), $content
            );
        }

        return $this->assemble($objects);
    }

    /** @param  array<int, string>  $objects */
    private function assemble(array $objects): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * Convertit en WinAnsi et échappe les caractères réservés du PDF.
     *
     * Sans conversion, « é » sortirait en UTF-8 sur deux octets et
     * s'afficherait comme deux caractères parasites.
     */
    private function escape(string $value): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        }

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $converted);
    }

    private function ensureRoom(float $needed): void
    {
        if ($this->cursor - $needed < self::MARGIN) {
            $this->newPage();
        }
    }
}
