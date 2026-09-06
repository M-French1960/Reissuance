<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BlindIndex;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlindIndexTest extends TestCase
{
    #[Test]
    public function la_normalisation_absorbe_les_variations_de_saisie(): void
    {
        $variantes = ['AB-123 456', 'ab123456', ' AB.123.456 ', 'Ab 123-456'];

        $attendu = BlindIndex::hash($variantes[0]);

        foreach ($variantes as $v) {
            $this->assertSame($attendu, BlindIndex::hash($v), "Variante non normalisée : {$v}");
        }
    }

    #[Test]
    public function deux_numeros_differents_donnent_des_empreintes_differentes(): void
    {
        $this->assertNotSame(BlindIndex::hash('AB123456'), BlindIndex::hash('AB123457'));
    }

    #[Test]
    public function l_empreinte_ne_laisse_pas_lire_le_numero(): void
    {
        $numero = 'AB123456';
        $empreinte = BlindIndex::hash($numero);

        $this->assertSame(64, strlen($empreinte));
        $this->assertStringNotContainsStringIgnoringCase($numero, $empreinte);
        $this->assertStringNotContainsString('123456', $empreinte);
    }
}
