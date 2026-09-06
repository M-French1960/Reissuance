<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Gouvernance des comptes. Reference : docs/PERMISSIONS.md 3.3.
 *
 * Separation stricte : l'administrateur gere les comptes et n'accede a aucun
 * contenu de dossier d'identite. Cette Policy ne lui ouvre donc rien d'autre.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin || $actor->id === $target->id;
    }

    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin;
    }

    /**
     * Aucune suppression de compte, jamais.
     *
     * Un compte supprime rendrait orphelines des lignes d'audit et des
     * decisions signees. La desactivation est la seule operation, et elle est
     * reversible.
     */
    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    public function changeStatus(User $actor, User $target): bool
    {
        // Un administrateur ne se desactive pas lui-meme : il se
        // verrouillerait dehors, et le systeme pourrait se retrouver sans
        // aucun administrateur actif.
        return $actor->role === UserRole::Admin && $actor->id !== $target->id;
    }

    /** Declenche un lien de reinitialisation ; ne choisit jamais le mot de passe. */
    public function triggerPasswordReset(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin;
    }

    public function reassign(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin && $target->role->isOfficial();
    }

    /** Metadonnees d'audit seulement : qui a consulte quoi, jamais le contenu. */
    public function viewAuditTrail(User $actor): bool
    {
        return $actor->role === UserRole::Admin;
    }
}
