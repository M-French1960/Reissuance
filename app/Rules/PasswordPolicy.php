<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Politique de mot de passe unique du projet (4.1 du brief).
 *
 * Definie a un seul endroit : inscription, reinitialisation et changement
 * doivent appliquer exactement les memes regles. Des politiques divergentes
 * creent une porte d'entree par le chemin le plus permissif.
 */
final class PasswordPolicy
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        $password = Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols();

        // uncompromised() interroge un service distant et laisse passer si
        // celui-ci est injoignable. On l'active quand meme — c'est une
        // exigence du brief et elle a de la valeur en ligne — mais le
        // plancher reel hors ligne est NotAWeakPassword.
        if (config('phoenix.security.check_compromised_passwords')) {
            $password = $password->uncompromised();
        }

        return ['required', 'string', 'confirmed', $password, new NotAWeakPassword];
    }
}
