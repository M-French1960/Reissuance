<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Support\Tracking\RequestTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Suivi d'une demande : frise chronologique du 8.1 du brief.
 *
 * « Le citoyen voit une frise chronologique de sa demande : ce qui est fait,
 * ce qui est en cours, ce qui reste. » Aucun delai chiffre n'est affiche : la
 * question D8 de COMPLIANCE_OPEN_QUESTIONS.md est ouverte, et inventer un
 * delai serait pire que ne rien annoncer.
 */
class RequestTrackingController extends Controller
{
    public function __construct(private readonly RequestTimeline $frise) {}

    public function index(Request $request): View
    {
        return view('citizen.requests.index', [
            'requests' => ReissuanceRequest::query()
                ->with('center:id,name')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, ReissuanceRequest $reissuanceRequest): View
    {
        $this->authorize('view', $reissuanceRequest);

        return view('citizen.requests.show', [
            'demande' => $reissuanceRequest->load('center.commune', 'attachments', 'signature.mayor'),
            'etapes' => $this->frise->for($reissuanceRequest),
            'refus' => $this->motifDuRefus($reissuanceRequest),
            'messages' => $reissuanceRequest->messages()->with('author:id,name')->oldest()->get(),
        ]);
    }

    /**
     * Le motif du refus, dit au demandeur (D-075).
     *
     * IL NE L'ETAIT PAS. Le motif est obligatoire pour refuser — le
     * controleur l'exige et la contrainte
     * request_decisions_reason_required_check l'impose en base — et l'agent
     * qui le saisit lit « Il sera visible dans le dossier ». Il ne l'etait
     * nulle part pour le citoyen : l'ecran de suivi affichait « Refusée » et
     * rien d'autre. Pire, la page des notifications le renvoyait ici :
     * « Le détail d'une demande — MOTIF D'UN REFUS COMPRIS — se lit sur la
     * page de la demande. » Elle le renvoyait vers une page qui se taisait.
     *
     * Refuser a quelqu'un un acte d'etat civil sans lui en donner la raison
     * n'est pas un defaut d'affichage.
     *
     * CE QUI N'EST PAS MONTRE, ET POURQUOI :
     *
     * - `internal_notes` : jamais. C'est le champ prevu pour ce qui reste
     *   entre agents, et l'existence meme de ce champ dit que `reason`, lui,
     *   se montre.
     * - l'escalade : elle n'est pas une decision defavorable mais une etape
     *   interne — le dossier est toujours en cours d'instruction. Annoncer
     *   « doute sur l'authenticite de la piece » a quelqu'un dont le dossier
     *   est encore en arbitrage renseignerait aussi une fraude en cours. La
     *   frise dit deja « Transmise au maire pour arbitrage ». Ce choix est un
     *   arbitrage a confirmer avec le client, pas une evidence.
     */
    private function motifDuRefus(ReissuanceRequest $demande): ?string
    {
        if ($demande->status !== RequestStatus::Rejected) {
            return null;
        }

        return $demande->decisions()
            ->where('decision', DecisionType::Rejected->value)
            ->latest('created_at')
            ->first()?->reason;
    }
}
