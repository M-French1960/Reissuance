<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ReissuanceRequest;
use App\Models\User;

/**
 * Autorisation sur les demandes. Reference : docs/PERMISSIONS.md 3.1.
 *
 * Refus par defaut : chaque methode retourne false sauf cas explicite.
 * Cette Policy double la portee globale plutot que de la remplacer : la portee
 * evite les fuites de liste, la Policy protege l'acces unitaire.
 */
class ReissuanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        // L'administrateur n'a acces a aucune demande, meme en liste.
        return $user->role !== UserRole::Admin;
    }

    public function view(User $user, ReissuanceRequest $request): bool
    {
        return match ($user->role) {
            UserRole::Citizen => $request->user_id === $user->id,

            UserRole::Officer => $request->civil_status_center_id === $user->civil_status_center_id
                && $request->status !== RequestStatus::Draft,

            UserRole::Mayor => $request->commune_id === $user->commune_id
                && in_array($request->status, [
                    RequestStatus::AwaitingSignature,
                    RequestStatus::Escalated,
                ], true),

            UserRole::Admin => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Citizen;
    }

    public function update(User $user, ReissuanceRequest $request): bool
    {
        // Un citoyen ne modifie que son propre brouillon.
        return $user->role === UserRole::Citizen
            && $request->user_id === $user->id
            && $request->status === RequestStatus::Draft;
    }

    /** Seule suppression autorisee de tout le systeme : son propre brouillon. */
    public function delete(User $user, ReissuanceRequest $request): bool
    {
        return $this->update($user, $request);
    }

    /**
     * Annulation par le demandeur — « Cancel Request » du diagramme.
     *
     * Tant que PERSONNE n'a pris le dossier en charge. Au-dela, annuler
     * jetterait le travail d'un officier ; le demandeur passe alors par
     * « Contact Officer ». Voir docs/CAS_USAGE.md 4.5.
     */
    public function cancel(User $user, ReissuanceRequest $request): bool
    {
        return $user->role === UserRole::Citizen
            && $request->user_id === $user->id
            && in_array($request->status, [RequestStatus::Draft, RequestStatus::Pending], true)
            && $request->assigned_officer_id === null;
    }

    /**
     * Ecrire dans le fil du dossier — « Contact Officer ».
     *
     * Le demandeur, l'officier du centre et le maire de la commune. PAS
     * l'administrateur : le 4.2 lui interdit le contenu des dossiers, et un
     * echange sur un dossier EST du contenu de dossier.
     *
     * Pas non plus sur un brouillon : il n'y a personne en face.
     */
    public function message(User $user, ReissuanceRequest $request): bool
    {
        if ($request->status === RequestStatus::Draft) {
            return false;
        }

        return match ($user->role) {
            UserRole::Citizen => $request->user_id === $user->id,
            UserRole::Officer => $request->civil_status_center_id === $user->civil_status_center_id,
            UserRole::Mayor => $request->commune_id === $user->commune_id,
            UserRole::Admin => false,
        };
    }

    public function submit(User $user, ReissuanceRequest $request): bool
    {
        return $this->update($user, $request);
    }

    /**
     * Prendre en charge un dossier de son centre que personne ne tient.
     *
     * `under_review` est accepte en plus de `pending` — et c'est la reprise
     * d'un dossier libere par l'administrateur (D-057). Sans cela, liberer une
     * affectation ne servirait a rien : le dossier resterait a l'etat
     * « en cours d'examen », que la prise en charge aurait refuse, et personne
     * ne pourrait plus le traiter.
     *
     * Aucune transition d'etat n'est en jeu ici : un dossier deja en cours
     * d'examen le reste, et les etapes de verification deja franchies sont
     * conservees. Seule l'affectation change.
     */
    public function claim(User $user, ReissuanceRequest $request): bool
    {
        return $user->role === UserRole::Officer
            && $request->civil_status_center_id === $user->civil_status_center_id
            && in_array($request->status, [RequestStatus::Pending, RequestStatus::UnderReview], true)
            && $request->assigned_officer_id === null;
    }

    /** Decider revient a l'officier qui a pris le dossier en charge. */
    public function decide(User $user, ReissuanceRequest $request): bool
    {
        return $user->role === UserRole::Officer
            && $request->civil_status_center_id === $user->civil_status_center_id
            && $request->status === RequestStatus::UnderReview
            && $request->assigned_officer_id === $user->id;
    }

    public function sign(User $user, ReissuanceRequest $request): bool
    {
        return $user->role === UserRole::Mayor
            && $request->commune_id === $user->commune_id
            && in_array($request->status, [
                RequestStatus::AwaitingSignature,
                RequestStatus::Escalated,
            ], true);
    }

    /** T8 et T11 : retour a l'officier, motif obligatoire. */
    public function returnToOfficer(User $user, ReissuanceRequest $request): bool
    {
        return $this->sign($user, $request);
    }

    /**
     * Consultation d'une piece d'identite.
     *
     * L'administrateur est exclu sans exception (4.2). Toute consultation
     * accordee est journalisee par le controleur qui sert le fichier.
     */
    public function viewIdentityDocuments(User $user, ReissuanceRequest $request): bool
    {
        return $user->role !== UserRole::Admin && $this->view($user, $request);
    }

    /**
     * Liberer une affectation bloquee — administrateur uniquement (D-057).
     *
     * L'administrateur ne voit aucun dossier et n'en decide aucun. Il agit ici
     * sur la consequence d'une action qui lui appartient deja : suspendre un
     * agent, ou le rattacher ailleurs, laisse des dossiers que plus personne
     * ne peut traiter. Liberer l'affectation ne touche ni l'etat de la demande
     * ni son contenu — elle redevient simplement prenable par le centre.
     *
     * Le role est le seul critere, deliberement : un officier ne doit jamais
     * pouvoir se defaire d'un dossier ni reprendre celui d'un collegue sans
     * passer par l'administration, sous peine de rendre le choix de l'agent
     * negociable — ce qui est exactement le levier d'une fraude.
     */
    public function releaseAssignment(User $user, ReissuanceRequest $request): bool
    {
        return $user->role === UserRole::Admin
            && $request->assigned_officer_id !== null
            && ! $request->status->isTerminal();
    }
}
