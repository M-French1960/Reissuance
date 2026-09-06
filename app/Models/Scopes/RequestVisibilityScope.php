<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Portee globale sur les demandes.
 *
 * C'est la reponse au principe n3 de docs/PERMISSIONS.md : un `where` oublie
 * dans une requete ne doit pas pouvoir faire fuir une donnee hors perimetre.
 * La restriction est appliquee ici, systematiquement, plutot que d'esperer que
 * chaque appel y pense. Test de refus R13.
 *
 * Non applique quand aucun utilisateur n'est authentifie (console, files,
 * seeders) : dans ce cas c'est l'appelant qui porte la responsabilite.
 */
final class RequestVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $table = $model->getTable();

        match ($user->role) {
            // Le citoyen ne voit que ses propres demandes, brouillons compris.
            UserRole::Citizen => $builder->where("{$table}.user_id", $user->id),

            // L'officier voit les demandes de SON centre, jamais un brouillon :
            // un brouillon n'existe que pour son auteur.
            UserRole::Officer => $builder
                ->where("{$table}.civil_status_center_id", $user->civil_status_center_id)
                ->where("{$table}.status", '!=', RequestStatus::Draft->value),

            // Le maire ne voit que sa commune, et seulement aux deux etats ou
            // il a competence. Une demande en pending ou under_review de sa
            // commune lui reste invisible (4.2 du brief).
            UserRole::Mayor => $builder
                ->where("{$table}.commune_id", $user->commune_id)
                ->whereIn("{$table}.status", [
                    RequestStatus::AwaitingSignature->value,
                    RequestStatus::Escalated->value,
                ]),

            // L'administrateur ne voit AUCUNE demande. Il gere les comptes,
            // pas les dossiers d'identite. C'est le point le plus
            // contre-intuitif de la matrice, et le plus important.
            UserRole::Admin => $builder->whereRaw('1 = 0'),
        };
    }
}
