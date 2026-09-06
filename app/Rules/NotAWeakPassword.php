<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Filet local contre les mots de passe evidents.
 *
 * Raison d'etre : la regle uncompromised() de Laravel interroge l'API
 * api.pwnedpasswords.com et, si elle est injoignable, **echoue en mode
 * ouvert** — NotPwnedVerifier attrape l'exception, retourne un corps vide,
 * et le mot de passe passe. Sur une installation locale sans acces internet,
 * la verification de fuites ne verifie donc rien du tout.
 *
 * Cette regle ne remplace pas uncompromised() : elle garantit un plancher qui
 * fonctionne hors ligne. Elle est volontairement courte et ciblee ; une vraie
 * liste de mots de passe compromis se compte en centaines de millions et n'a
 * pas sa place dans le depot.
 */
final class NotAWeakPassword implements ValidationRule
{
    /**
     * Termes interdits, compares apres normalisation.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'password', 'motdepasse', 'passe', 'azerty', 'qwerty', 'admin',
        'administrateur', 'phoenix', 'cameroun', 'yaounde', 'douala',
        'etatcivil', 'naissance', 'certificat', 'officier', 'maire',
        'citoyen', 'bienvenue', 'welcome', 'changeme', 'secret',
        '123456', '12345678', '000000', '111111', 'abcdef',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalised = $this->normalise($value);

        foreach (self::FORBIDDEN as $term) {
            if (str_contains($normalised, $term)) {
                $fail('Ce mot de passe contient un terme trop courant. Choisissez une suite de mots sans rapport avec le service.');

                return;
            }
        }

        if ($this->isRepeatedOrSequential($normalised)) {
            $fail('Ce mot de passe suit une suite trop simple. Variez les caractères.');
        }
    }

    private function normalise(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');

        // Rapproche les substitutions les plus communes : p@ssw0rd -> password.
        return strtr($lower, [
            '@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i', '!' => 'i',
            '0' => 'o', '5' => 's', '$' => 's', '7' => 't',
        ]);
    }

    private function isRepeatedOrSequential(string $value): bool
    {
        // Un seul caractere repete.
        if (preg_match('/^(.)\1+$/u', $value) === 1) {
            return true;
        }

        // Six caracteres consecutifs croissants ou decroissants.
        $length = mb_strlen($value);

        for ($i = 0; $i + 5 < $length; $i++) {
            $up = true;
            $down = true;

            for ($j = 0; $j < 5; $j++) {
                $delta = mb_ord(mb_substr($value, $i + $j + 1, 1)) - mb_ord(mb_substr($value, $i + $j, 1));
                $up = $up && $delta === 1;
                $down = $down && $delta === -1;
            }

            if ($up || $down) {
                return true;
            }
        }

        return false;
    }
}
