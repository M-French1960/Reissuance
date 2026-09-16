<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Money;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An amount is spelled in the reader's language, and is the same amount (D-077).
 *
 * THE DEFECT THIS GUARDS. `number_format(..., ',', ' ')` was written into
 * Money::format(), so the French separators applied whatever the language. An
 * English payment receipt printed "1 500 XAF" where an English reader expects
 * "1,500 XAF". Found by reading a receipt in English, not by a test.
 *
 * What must never change with the language is the SUM. Only its spelling does.
 */
class MoneyFormattingTest extends TestCase
{
    /** XAF has no subdivision: a thousands separator, no decimals. */
    #[Test]
    public function un_montant_suit_les_separateurs_de_la_langue(): void
    {
        $montant = new Money(1500, 'XAF', 0);

        app()->setLocale('en');
        $this->assertSame('1,500 XAF', $montant->format());

        app()->setLocale('fr');

        /*
         * UNE ESPACE ORDINAIRE, PAS UNE INSECABLE, ET C'EST UN ARBITRAGE.
         *
         * La typographie francaise appelle une insecable entre les milliers.
         * Mais le client a demande que les espaces insecables disparaissent des
         * interfaces, et TypographyTest les refuse dans les fichiers de langue.
         *
         * L'espace ordinaire l'emporte : c'est ce qu'un developpeur francophone
         * tape dans un number_format, et le seul cout est qu'un montant pourrait
         * se couper en fin de ligne. Sur des frais a quatre chiffres, le risque
         * est cosmetique. Si le client prefere l'insecable, c'est ici et dans
         * TypographyTest que la decision se change.
         */
        $this->assertSame('1 500 XAF', $montant->format());
    }

    /** And a currency with decimals keeps them, spelled per language. */
    #[Test]
    public function un_montant_a_decimales_suit_aussi_la_langue(): void
    {
        $montant = new Money(123456, 'EUR', 2);

        app()->setLocale('en');
        $this->assertSame('1,234.56 EUR', $montant->format());

        app()->setLocale('fr');
        $this->assertSame('1 234,56 EUR', $montant->format());
    }

    /**
     * THE SUM DOES NOT MOVE.
     *
     * Stripping everything but the digits must give the same figure in both
     * languages. A separator swap that changed the number would be a billing
     * defect, not a typography one.
     */
    #[Test]
    public function la_somme_est_la_meme_dans_les_deux_langues(): void
    {
        $montant = new Money(9876543, 'XAF', 0);

        app()->setLocale('en');
        $anglais = preg_replace('/\D/', '', explode(' XAF', $montant->format())[0]);

        app()->setLocale('fr');
        $francais = preg_replace('/\D/', '', explode(' XAF', $montant->format())[0]);

        $this->assertSame('9876543', $anglais);
        $this->assertSame($anglais, $francais);
    }
}
