<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Support\Tracking\RequestTimeline;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Les trois groupes d'onglets (D-100).
     *
     * `action` rassemble ce que le DEMANDEUR doit faire lui-meme : reprendre
     * un brouillon, ou envoyer une piece reclamee. C'est le seul onglet qui
     * compte vraiment — les deux autres se regardent, celui-ci s'agit.
     *
     * @var list<string>
     */
    private const ONGLETS = ['all', 'action', 'open', 'closed'];

    public function index(Request $request): View
    {
        $filtre = (string) $request->input('filtre', 'all');

        if (! in_array($filtre, self::ONGLETS, true)) {
            $filtre = 'all';
        }

        $onglets = [];

        foreach (self::ONGLETS as $onglet) {
            $onglets[$onglet] = $this->filtrer(ReissuanceRequest::query(), $onglet)->count();
        }

        return view('citizen.requests.index', [
            'requests' => $this->filtrer(ReissuanceRequest::query(), $filtre)
                ->with('center:id,name')
                /*
                 * LE COMPTE EST CHARGE ICI, ET PAS LU PAR LA VUE.
                 *
                 * La carte doit savoir si une piece est attendue du demandeur.
                 * Le demander a chaque carte ferait une requete PAR LIGNE, ce
                 * que le budget de requetes refuse — et un accesseur qui
                 * retomberait silencieusement sur zero quand le compte n'est
                 * pas charge serait pire : il MENTIRAIT au demandeur sur ce
                 * qu'on attend de lui.
                 */
                ->withCount(['complements as pending_complements' => fn ($q) => $q->whereNull('fulfilled_at')])
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'filtre' => $filtre,
            'onglets' => $onglets,
            'total' => $onglets['all'],
        ]);
    }

    /**
     * @param  Builder<ReissuanceRequest>  $requete
     * @return Builder<ReissuanceRequest>
     */
    private function filtrer($requete, string $onglet)
    {
        $termines = array_map(
            static fn (RequestStatus $etat): string => $etat->value,
            array_filter(RequestStatus::cases(), static fn (RequestStatus $etat): bool => $etat->isTerminal()),
        );

        return match ($onglet) {
            // Ce que le demandeur doit faire : un brouillon a reprendre, ou
            // une piece reclamee qu'il n'a pas encore envoyee.
            'action' => $requete->where(fn ($q) => $q
                ->where('status', RequestStatus::Draft->value)
                ->orWhereHas('complements', fn ($c) => $c->whereNull('fulfilled_at'))),

            'open' => $requete
                ->whereNotIn('status', [...$termines, RequestStatus::Draft->value])
                ->whereDoesntHave('complements', fn ($c) => $c->whereNull('fulfilled_at')),

            'closed' => $requete->whereIn('status', $termines),

            default => $requete,
        };
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
