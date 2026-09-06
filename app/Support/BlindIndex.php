<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Index aveugle pour la recherche sur un champ chiffre.
 *
 * Le numero de piece est chiffre (6 du brief), donc non interrogeable : ni
 * WHERE, ni LIKE, ni index. Or l'officier doit pouvoir le rechercher
 * (5.3, etape 4). On stocke a cote une empreinte HMAC permettant
 * l'egalite stricte, et rien d'autre.
 *
 * Ce que cela permet : recherche par numero exact, detection de doublons.
 * Ce que cela ne permet pas : recherche partielle, tri, lecture par acces
 * direct a la base. Voir docs/DATA_MODEL.md 3.
 */
final class BlindIndex
{
    /**
     * Normalise puis calcule l'empreinte.
     *
     * La normalisation est centralisee ici et nulle part ailleurs : deux
     * normalisations divergentes produiraient des empreintes differentes
     * pour un meme numero, et la recherche echouerait silencieusement.
     */
    public static function hash(string $value): string
    {
        $normalised = self::normalise($value);

        if ($normalised === '') {
            throw new RuntimeException('Valeur vide : aucune empreinte calculable.');
        }

        return hash_hmac('sha256', $normalised, self::key());
    }

    public static function normalise(string $value): string
    {
        // Majuscules, et suppression de tout ce qui n'est ni lettre ni chiffre :
        // espaces, tirets et points varient d'une saisie a l'autre.
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value), 'UTF-8')) ?? '';
    }

    private static function key(): string
    {
        $key = (string) config('phoenix.blind_index_key');

        if ($key === '') {
            throw new RuntimeException(
                'PHOENIX_BLIND_INDEX_KEY absente. Générer la clé avec : php artisan phoenix:generate-index-key'
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('PHOENIX_BLIND_INDEX_KEY : encodage base64 invalide.');
            }

            return $decoded;
        }

        return $key;
    }
}
