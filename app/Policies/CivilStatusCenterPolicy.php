<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CivilStatusCenter;
use App\Models\User;

/**
 * Raccordement des centres d'etat civil. Reference : docs/PERMISSIONS.md 3.3.
 *
 * CE QUE CET ECRAN FAIT, ET CE QU'IL NE FAIT PAS. Un centre d'etat civil
 * n'existe pas parce que quelqu'un a clique : il existe par un acte
 * administratif qui ne passe pas par cette plateforme. Ce que
 * l'administrateur fait ici, c'est RACCORDER un centre existant, ou le
 * debrancher. Le vocabulaire de l'interface le dit (§10 : aucune hypothese
 * juridique codee en dur).
 *
 * LE STATUT DU COMPTE N'EST PAS VERIFIE ICI, comme dans UserPolicy : un
 * administrateur suspendu est refuse avant d'y arriver, par
 * `AccountStatusGate` (D-059).
 */
class CivilStatusCenterPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function update(User $actor, CivilStatusCenter $center): bool
    {
        return $actor->role === UserRole::Admin;
    }

    /**
     * Aucune suppression de centre, jamais.
     *
     * Un centre supprime rendrait orphelines des demandes envoyees, des
     * decisions et des lignes d'audit — et la cle etrangere le refuserait de
     * toute facon. Debrancher est la seule operation, et elle est reversible.
     */
    public function delete(User $actor, CivilStatusCenter $center): bool
    {
        return false;
    }

    /**
     * Le code du registre ne se corrige que sur un centre encore vierge.
     *
     * Le code identifie le centre pour tout le reste du systeme, et il est
     * affiche au citoyen. Le changer sur un centre qui porte deja des demandes
     * romprait la correspondance entre ce que le citoyen a lu et ce que la
     * base contient. Une faute de frappe se rattrape donc tant qu'aucune
     * demande n'y est arrivee, et jamais apres.
     *
     * POURQUOI LE NOMBRE EST UN ARGUMENT, ET NON COMPTE ICI. Compter les
     * demandes d'un centre exige de contourner la portee globale, qui rend
     * l'administrateur aveugle a toute demande — sans contournement, le compte
     * vaudrait toujours zero et le code serait toujours modifiable. Ce
     * contournement vit dans UN SEUL endroit, garde par un test
     * (`ScopeBypassTest`). La Policy reste donc une regle pure, et l'appelant
     * lui passe le nombre qu'il a obtenu de la methode auditee.
     */
    public function changeCode(User $actor, CivilStatusCenter $center, int $receivedRequests): bool
    {
        return $actor->role === UserRole::Admin && $receivedRequests === 0;
    }
}
