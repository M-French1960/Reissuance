<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function un_montant_sans_subdivision_se_lit_sans_virgule(): void
    {
        $this->assertSame('1 000 XAF', (new Money(1000, 'XAF'))->format());
        $this->assertSame('0 XAF', (new Money(0, 'XAF'))->format());
    }

    #[Test]
    public function une_devise_a_subdivision_se_lit_avec_ses_decimales(): void
    {
        $this->assertSame('12,34 EUR', (new Money(1234, 'EUR', 2))->format());
    }

    #[Test]
    public function un_montant_negatif_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(-1, 'XAF');
    }

    /** @return list<array{string}> */
    public static function devisesInvalides(): array
    {
        return [['xaf'], ['XA'], ['XAFR'], [''], ['123']];
    }

    #[Test]
    #[DataProvider('devisesInvalides')]
    public function une_devise_hors_iso_4217_est_refusee(string $devise): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Money(1000, $devise);
    }

    /**
     * LE test qui compte : sans tarif configure, on refuse de servir.
     *
     * Le mode de defaillance a eviter est un montant par defaut. Un tarif de
     * service public se lit dans un texte reglementaire (D-039).
     */
    #[Test]
    public function sans_tarif_configure_la_construction_echoue_avec_un_message_utilisable(): void
    {
        config(['phoenix.payments.amount_minor' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Aucun tarif/');

        Money::fromConfig();
    }

    #[Test]
    public function un_tarif_a_virgule_est_refuse(): void
    {
        config(['phoenix.payments.amount_minor' => '1000.50']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/n'est pas un entier/");

        Money::fromConfig();
    }

    #[Test]
    public function un_tarif_configure_est_repris_tel_quel(): void
    {
        config([
            'phoenix.payments.amount_minor' => '1500',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
        ]);

        $montant = Money::fromConfig();

        $this->assertSame(1500, $montant->minorAmount);
        $this->assertSame('1 500 XAF', $montant->format());
    }

    /**
     * Aucun montant n'est code dans le depot.
     *
     * Le prototype affichait 20 000 CFA, valeur invérifiable (D-003). Ce test
     * echoue si un chiffre reapparait dans le code ou dans les vues.
     */
    #[Test]
    public function aucun_montant_n_est_code_en_dur_dans_le_depot(): void
    {
        $suspects = [];

        foreach ([app_path(), resource_path('views')] as $racine) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $fichier) {
                if (! $fichier->isFile()) {
                    continue;
                }

                $nom = $fichier->getFilename();

                if (! str_ends_with($nom, '.php')) {
                    continue;
                }

                // Un nombre de quatre chiffres ou plus a cote d'une mention
                // monetaire : c'est la forme qu'avait le tarif du prototype.
                if (preg_match('/\b\d{4,}\s*(F\s*CFA|FCFA|XAF)\b/i', file_get_contents($fichier->getPathname()))) {
                    $suspects[] = str_replace(base_path().'/', '', $fichier->getPathname());
                }
            }
        }

        $this->assertSame([], $suspects, 'Montant codé en dur : '.implode(', ', $suspects));
    }
}
