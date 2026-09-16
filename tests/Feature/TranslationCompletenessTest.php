<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Locales;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every language serves the same interface (D-076).
 *
 * WHAT THIS GUARDS. A key added to one language and forgotten in the other
 * does not raise an error: Laravel prints the key itself. A citizen reading
 * the service in French would be shown `citizen.tracking.rejected_title` where
 * the reason for the rejection of their certificate should be.
 *
 * English is the platform default and the reference here, but the check runs
 * both ways: a key that exists only in French is just as broken, it simply
 * breaks for the other half of the country.
 *
 * It also catches an empty string, which renders as nothing at all and is
 * harder to notice than a raw key.
 */
class TranslationCompletenessTest extends TestCase
{
    /**
     * Flattens a language file into dotted keys.
     *
     * @param  array<string, mixed>  $tableau
     * @return array<string, string>
     */
    private function aplatir(array $tableau, string $prefixe = ''): array
    {
        $plat = [];

        foreach ($tableau as $cle => $valeur) {
            $chemin = $prefixe === '' ? (string) $cle : $prefixe.'.'.$cle;

            if (is_array($valeur)) {
                $plat += $this->aplatir($valeur, $chemin);

                continue;
            }

            $plat[$chemin] = (string) $valeur;
        }

        return $plat;
    }

    /** @return array<string, string> every key of one language */
    private function cles(string $locale): array
    {
        $toutes = [];

        $dossier = lang_path($locale);

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $groupe = basename($fichier->getFilename(), '.php');
            $contenu = require $fichier->getPathname();

            $toutes += $this->aplatir(is_array($contenu) ? $contenu : [], $groupe);
        }

        return $toutes;
    }

    /** Every language carries exactly the same keys. */
    #[Test]
    public function les_deux_langues_portent_les_memes_cles(): void
    {
        $reference = array_keys($this->cles(Locales::DEFAULT));
        sort($reference);

        foreach (array_keys(Locales::SUPPORTED) as $locale) {
            if ($locale === Locales::DEFAULT) {
                continue;
            }

            $autres = array_keys($this->cles($locale));
            sort($autres);

            $manquantes = array_values(array_diff($reference, $autres));
            $enTrop = array_values(array_diff($autres, $reference));

            $this->assertSame([], $manquantes, implode("\n", array_merge(
                ["Keys present in '".Locales::DEFAULT."' and missing from '{$locale}':"],
                $manquantes,
            )));

            $this->assertSame([], $enTrop, implode("\n", array_merge(
                ["Keys present in '{$locale}' and missing from '".Locales::DEFAULT."':"],
                $enTrop,
            )));
        }
    }

    /** And no key resolves to nothing. */
    #[Test]
    public function aucune_traduction_n_est_vide(): void
    {
        $vides = [];

        foreach (array_keys(Locales::SUPPORTED) as $locale) {
            foreach ($this->cles($locale) as $cle => $valeur) {
                /*
                 * Un separateur de milliers PEUT etre une espace, et c'en est
                 * une en francais. Une valeur qui n'est faite que d'espaces
                 * reste un oubli partout ailleurs, donc l'exemption est
                 * nommee plutot que la regle assouplie.
                 */
                $estSeparateur = str_ends_with($cle, '_separator');

                if ($estSeparateur ? $valeur === '' : trim($valeur) === '') {
                    $vides[] = "{$locale}: {$cle}";
                }
            }
        }

        $this->assertSame([], $vides, implode("\n", array_merge(
            ['These keys resolve to an empty string, so they render as nothing:'],
            $vides,
        )));
    }

    /**
     * Every placeholder of a key exists in every language.
     *
     * A :reference that survives in one language and is dropped in the other
     * leaves one half of the country reading a sentence with a hole in it.
     */
    #[Test]
    public function les_memes_variables_sont_attendues_dans_chaque_langue(): void
    {
        $reference = $this->cles(Locales::DEFAULT);
        $ecarts = [];

        foreach (array_keys(Locales::SUPPORTED) as $locale) {
            if ($locale === Locales::DEFAULT) {
                continue;
            }

            foreach ($this->cles($locale) as $cle => $valeur) {
                if (! isset($reference[$cle])) {
                    continue;
                }

                if ($this->variables($reference[$cle]) !== $this->variables($valeur)) {
                    $ecarts[] = "{$locale}: {$cle}";
                }
            }
        }

        $this->assertSame([], $ecarts, implode("\n", array_merge(
            ['These keys do not expect the same placeholders in every language:'],
            $ecarts,
        )));
    }

    /** @return list<string> */
    private function variables(string $texte): array
    {
        preg_match_all('/:([a-z_]+)/', $texte, $trouvees);

        $noms = array_unique($trouvees[1]);
        sort($noms);

        return array_values($noms);
    }
}
