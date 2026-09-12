<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rendu d'un gabarit Blade en PDF, avec le moteur bride.
 *
 * POURQUOI CETTE CLASSE EXISTE plutot qu'un appel direct a Dompdf : un moteur
 * de rendu HTML qui traite du contenu venu d'un dossier citoyen est une
 * surface d'attaque. Les options qui comptent sont posees ici, en un seul
 * endroit, et un test verifie qu'elles le sont — pas dispersees dans chaque
 * appelant, ou la prochaine addition les oubliera.
 *
 * TROIS BRIDES, ET CE QU'ELLES EMPECHENT :
 *
 *   - `isRemoteEnabled` a faux : le moteur ne va chercher AUCUNE ressource
 *     distante. Sans cela, un `<img src="http://...">` glisse dans une donnee
 *     de dossier ferait emettre au serveur une requete sortante choisie par
 *     un tiers — une requete depuis l'interieur du reseau de la mairie.
 *   - `isPhpEnabled` a faux : pas de `<script type="text/php">`, donc pas
 *     d'execution de code depuis le gabarit.
 *   - `isJavascriptEnabled` a faux : pas de JavaScript embarque dans le PDF
 *     produit. Un lecteur de PDF qui l'executerait le ferait chez le citoyen.
 *
 * `chroot` limite en outre les chemins locaux lisibles au seul dossier des
 * gabarits : meme un `<img src="/etc/passwd">` n'aurait rien a lire.
 *
 * Les gabarits eux-memes n'emploient aucune ressource externe : ces brides
 * sont la pour le jour ou quelqu'un en ajoutera une sans y penser.
 */
final class HtmlToPdf
{
    /** @var array<string, bool|string> les brides, relues par le test */
    public const HARDENING = [
        'isRemoteEnabled' => false,
        'isPhpEnabled' => false,
        'isJavascriptEnabled' => false,
    ];

    /**
     * Rend un gabarit Blade en PDF.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function render(string $vue, array $donnees = []): string
    {
        $html = view($vue, $donnees)->render();

        $options = new Options;

        foreach (self::HARDENING as $cle => $valeur) {
            $options->set($cle, $valeur);
        }

        // Les gabarits n'ouvrent aucun fichier local ; on limite tout de meme
        // ce qui serait lisible si l'un d'eux venait a en ouvrir un.
        $options->setChroot([resource_path('views/documents')]);
        $options->set('defaultFont', 'DejaVu Sans');

        $moteur = new Dompdf($options);
        $moteur->setPaper('A4');
        $moteur->loadHtml($html, 'UTF-8');
        $moteur->render();

        $pdf = $moteur->output();

        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException(
                "Le moteur PDF n'a pas produit de PDF. Aucun document n'est enregistré "
                .'à partir d’une sortie dont la nature est incertaine.'
            );
        }

        return $pdf;
    }
}
