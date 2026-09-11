<?php

declare(strict_types=1);

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Un compte qui n'est pas actif n'est autorise a rien.
 *
 * POURQUOI CETTE CLASSE EXISTE. Aucune des vingt-trois capacites des Policies
 * ne regardait le statut du compte. `ReissuanceRequestPolicy::decide()`
 * repondait **oui** pour un officier suspendu ; `UserPolicy::reassign()`
 * admettait l'administrateur, parce que `isOfficial()` n'exclut que le
 * citoyen. Ce n'etaient pas deux bogues distincts mais deux symptomes du meme
 * trou.
 *
 * En pratique rien ne passait : le middleware `EnsureAccountIsActive`
 * deconnecte un compte suspendu a la porte HTTP. Mais s'en remettre a lui a
 * deux defauts, et le second est le plus serieux :
 *
 *   1. les Policies sont aussi consultees HORS requete HTTP — file d'attente,
 *      commandes Artisan, semences — ou aucun middleware ne tourne ;
 *   2. **une Policy qu'on relit ne dit pas ce qu'elle applique.** Un
 *      developpeur qui lit `decide()` y voit trois conditions et en conclut,
 *      raisonnablement, qu'un agent suspendu est refuse par la Policy. Il ne
 *      l'est pas. Une barriere qu'on croit lire la ou elle n'est pas est
 *      exactement ce qui produit la faille suivante.
 *
 * POURQUOI ICI ET PAS DANS CHAQUE CAPACITE. Repeter `$user->isActive()`
 * vingt-trois fois, c'est vingt-trois occasions de l'oublier — et la
 * vingt-quatrieme capacite, ecrite dans six mois, ne l'aura pas. Une regle
 * unique, posee avant toute Policy, ne s'oublie pas.
 *
 * CE QUE CELA N'EMPECHE PAS : un compte en attente de configuration atteint
 * toujours son ecran de double authentification, qui n'est garde par aucune
 * Policy — verifie. C'est la seule chose qu'il doit pouvoir faire.
 *
 * Voir D-059.
 */
final class AccountStatusGate
{
    public static function register(): void
    {
        /*
         * `before` court-circuite TOUTE verification d'autorisation. Rendre
         * `false` refuse ; rendre `null` laisse les Policies decider.
         */
        Gate::before(static function (?User $user): ?bool {
            if ($user === null) {
                return null;
            }

            return $user->isActive() ? null : false;
        });
    }
}
