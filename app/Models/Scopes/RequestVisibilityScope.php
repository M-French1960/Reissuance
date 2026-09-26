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

            /*
             * Le maire ne voit que sa commune, et seulement aux etats ou il a
             * competence. Une demande en pending ou under_review de sa commune
             * lui reste invisible (4.2 du brief).
             *
             * S'Y AJOUTE CE QU'IL A LUI-MEME SIGNE (D-086). Une fois signee,
             * une demande sortait entierement de sa vue : le signataire ne
             * pouvait plus savoir ce qu'il avait signe, ni relire l'acte qui
             * porte sa signature. C'est intenable pour la personne qui en
             * repond.
             *
             * L'ouverture est aussi etroite que possible : l'etat « signe », la
             * commune du maire, ET une signature enregistree a SON nom. Un
             * autre maire de la meme commune — un adjoint, un successeur — ne
             * voit pas l'acte signe par son collegue. C'est ce que la
             * sous-requete impose, et un test l'exerce.
             *
             * Ce qu'elle N'ouvre PAS : aucune action. `sign` et
             * `returnToOfficer` exigent toujours « en attente de signature » ou
             * « escaladee », et la piece d'identite du citoyen reste fermee
             * apres la signature (voir ReissuanceRequestPolicy).
             */
            UserRole::Mayor => $builder
                ->where("{$table}.commune_id", $user->commune_id)
                ->where(function (Builder $portee) use ($table, $user): void {
                    $portee
                        ->whereIn("{$table}.status", [
                            RequestStatus::AwaitingSignature->value,
                            RequestStatus::Escalated->value,
                        ])
                        ->orWhere(function (Builder $signes) use ($table, $user): void {
                            $signes
                                ->where("{$table}.status", RequestStatus::Signed->value)
                                ->whereExists(function ($sous) use ($table, $user): void {
                                    $sous->selectRaw('1')
                                        ->from('document_signatures')
                                        ->whereColumn('document_signatures.request_id', "{$table}.id")
                                        ->where('document_signatures.mayor_id', $user->id);
                                });
                        });
                }),

            // L'administrateur ne voit AUCUNE demande. Il gere les comptes,
            // pas les dossiers d'identite. C'est le point le plus
            // contre-intuitif de la matrice, et le plus important.
            UserRole::Admin => $builder->whereRaw('1 = 0'),
        };
    }
}
