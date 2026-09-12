<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Support\Pdf\HtmlToPdf;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsPdfText;
use Tests\TestCase;

/**
 * Les brides du moteur PDF.
 *
 * Un moteur de rendu HTML qui traite du contenu venu d'un dossier citoyen est
 * une surface d'attaque. Ces tests verifient que les brides sont posees, et
 * surtout qu'elles TIENNENT : chacun donne au moteur ce qu'il est cense
 * refuser et verifie qu'il le refuse.
 */
class PdfEngineTest extends TestCase
{
    use ReadsPdfText;

    private function moteur(): HtmlToPdf
    {
        return new HtmlToPdf;
    }

    /** @param  array<string, mixed>  $donnees */
    private function rendu(string $html, array $donnees = []): string
    {
        // Un gabarit a la volee : on teste le moteur, pas les gabarits livres.
        View::addNamespace('essais', sys_get_temp_dir());
        $nom = 'phoenix-essai-'.bin2hex(random_bytes(6));
        file_put_contents(sys_get_temp_dir()."/{$nom}.blade.php", $html);

        try {
            return $this->moteur()->render("essais::{$nom}", $donnees);
        } finally {
            @unlink(sys_get_temp_dir()."/{$nom}.blade.php");
        }
    }

    #[Test]
    public function les_trois_brides_sont_declarees(): void
    {
        // Elles sont lues depuis la classe : si quelqu'un en retire une, ce
        // test le dit, meme si aucun scenario ci-dessous ne la couvre.
        $this->assertSame(
            ['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false],
            HtmlToPdf::HARDENING
        );
    }

    /**
     * LE test qui compte : aucune requete sortante.
     *
     * Sans cette bride, une adresse « http://... » glissee dans une donnee de
     * dossier ferait emettre au serveur de la mairie une requete choisie par
     * un tiers, depuis l'interieur de son reseau.
     */
    #[Test]
    public function aucune_ressource_distante_n_est_recuperee(): void
    {
        // 203.0.113.0/24 est reserve a la documentation (RFC 5737) : rien n'y
        // repond, donc un moteur qui tenterait l'appel s'y attarderait.
        $debut = microtime(true);

        $pdf = $this->rendu(
            '<p>avant</p><img src="http://203.0.113.7/pixel.png" alt=""><p>apres</p>'
        );

        $duree = microtime(true) - $debut;

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('avant', $this->extractText($pdf));
        $this->assertLessThan(
            5.0,
            $duree,
            'Le rendu a pris le temps d’un appel réseau : la bride isRemoteEnabled a sauté.'
        );
    }

    /** Pas de code execute depuis un gabarit. */
    #[Test]
    public function le_php_embarque_dans_le_html_n_est_pas_execute(): void
    {
        $pdf = $this->rendu(
            '<p>debut</p>'
            .'<script type="text/php">$GLOBALS["phoenix_pdf_php_execute"] = true;</script>'
            .'<p>fin</p>'
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertArrayNotHasKey(
            'phoenix_pdf_php_execute',
            $GLOBALS,
            'Le moteur a exécuté du PHP venu du gabarit : la bride isPhpEnabled a sauté.'
        );
    }

    /**
     * Le contenu d'un dossier est du TEXTE, pas du balisage.
     *
     * Blade echappe par defaut ; ce test verifie que les gabarits de documents
     * ne contournent pas cet echappement, parce qu'un nom contenant du
     * balisage pourrait sinon deformer l'acte.
     */
    #[Test]
    public function une_valeur_portant_du_balisage_est_imprimee_telle_quelle(): void
    {
        $pdf = $this->rendu('<p>Nom : {{ $valeur }}</p>', [
            'valeur' => '<b>GRAS</b> &amp; compagnie',
        ]);

        $texte = $this->extractText($pdf);

        $this->assertStringContainsString('<b>GRAS</b>', $texte);
        $this->assertStringContainsString('&amp; compagnie', $texte);
    }

    /** Une sortie qui n'est pas un PDF n'est jamais rendue comme telle. */
    #[Test]
    public function les_gabarits_livres_produisent_tous_un_pdf(): void
    {
        foreach (['documents.layout'] as $gabarit) {
            $this->assertStringStartsWith('%PDF-', $this->moteur()->render($gabarit));
        }
    }
}
