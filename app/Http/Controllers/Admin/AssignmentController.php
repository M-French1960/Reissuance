<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Affectations bloquees : les voir, et les liberer (D-057).
 *
 * POURQUOI CET ECRAN EXISTE. `claim` exige l'etat « en attente » et aucun
 * agent affecte ; `decide` exige l'etat « en cours d'examen » et l'affectation
 * a soi-meme. Un dossier dont l'agent affecte ne peut plus agir — suspendu,
 * desactive, ou rattache a un autre centre — n'etait donc repris par
 * personne. La demande d'un citoyen devenait definitivement intraitable, sans
 * qu'aucune alerte ne le signale.
 *
 * CE QUE L'ADMINISTRATEUR VOIT, ET CE QU'IL NE VOIT PAS. Il ne voit AUCUNE
 * demande : la portee globale le lui interdit, et c'est la regle la plus
 * importante de la matrice (4.2 du brief) — il gouverne les comptes, pas les
 * dossiers d'identite. Cet ecran ne montre donc pas des demandes mais des
 * AFFECTATIONS : une reference, un centre, un agent, une date. La selection
 * est verrouillee sur `ReissuanceRequest::ADMINISTRATION_COLUMNS`, qui ne
 * porte ni nom de naissance, ni filiation, ni piece jointe.
 *
 * CE QUE LIBERER FAIT, ET NE FAIT PAS. L'affectation est effacee, rien
 * d'autre : ni l'etat de la demande, ni les etapes de verification deja
 * franchies. Le dossier redevient prenable par un agent du centre, qui
 * reprendra ou le precedent s'est arrete.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $affectations = ReissuanceRequest::assignmentsForAdministration()
            ->with(['assignedOfficer:id,name,email,status,civil_status_center_id', 'center:id,name'])
            ->whereNotNull('assigned_officer_id')
            ->whereIn('status', [
                RequestStatus::Pending->value,
                RequestStatus::UnderReview->value,
            ])
            ->orderBy('submitted_at')
            ->get();

        // Le tri se fait ici et non en SQL : « l'agent peut-il encore agir ? »
        // croise son statut de compte ET son rattachement, et l'ecrire en SQL
        // le rendrait moins lisible que la regle qu'il applique.
        $bloquees = $affectations->filter(
            fn (ReissuanceRequest $d): bool => self::estBloquee($d)
        )->values();

        return view('admin.assignments.index', [
            'bloquees' => $bloquees,
            'actives' => $affectations->reject(
                fn (ReissuanceRequest $d): bool => self::estBloquee($d)
            )->values(),
        ]);
    }

    public function release(Request $request, int $demande): RedirectResponse
    {
        // Resolution explicite : la liaison automatique de modele passe par la
        // portee globale, qui rend l'administrateur aveugle a toute demande et
        // renvoyait donc 404 sur une action qui lui est pourtant reservee.
        $reissuanceRequest = ReissuanceRequest::assignmentsForAdministration()
            ->findOrFail($demande);

        $this->authorize('releaseAssignment', $reissuanceRequest);

        $precedent = $reissuanceRequest->assignedOfficer;

        DB::transaction(function () use ($request, $reissuanceRequest, $precedent): void {
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role->value,
                'action' => 'request.assignment_released',
                'auditable_type' => 'reissuance_request',
                'auditable_id' => $reissuanceRequest->id,
                'reason' => $precedent !== null
                    ? "Affectation libérée : {$precedent->name} ne peut plus traiter ce dossier."
                    : null,
                'ip_address' => $request->ip(),
            ]);

            $reissuanceRequest->forceFill(['assigned_officer_id' => null])->save();
        });

        return redirect()
            ->route('admin.assignments.index')
            ->with('status', "Dossier {$reissuanceRequest->reference} libéré : un agent du centre peut le reprendre.");
    }

    /**
     * L'agent affecte peut-il encore traiter ce dossier ?
     *
     * Deux causes, toutes deux consequences d'une action de l'administrateur :
     * le compte n'est plus actif, ou il a ete rattache a un autre centre.
     */
    public static function estBloquee(ReissuanceRequest $demande): bool
    {
        $agent = $demande->assignedOfficer;

        if ($agent === null) {
            return false;
        }

        return ! $agent->isActive()
            || $agent->civil_status_center_id !== $demande->civil_status_center_id;
    }
}
