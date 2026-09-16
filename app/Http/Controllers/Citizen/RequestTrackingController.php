<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
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
            'etapes' => $this->timeline($reissuanceRequest),
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

    /**
     * Construit la frise a partir du journal d'audit, qui est la seule trace
     * fiable de ce qui s'est reellement passe.
     *
     * @return list<array{titre: string, etat: string, detail: string, date: ?string}>
     */
    private function timeline(ReissuanceRequest $demande): array
    {
        $transitions = AuditLog::query()
            ->where('auditable_type', 'reissuance_request')
            ->where('auditable_id', $demande->id)
            ->whereNotNull('to_status')
            ->orderBy('created_at')
            ->get()
            ->keyBy('to_status');

        $statut = $demande->status;

        $jalons = [
            [
                'titre' => __('citizen.tracking.milestone_submitted'),
                'statut' => RequestStatus::Pending,
                /*
                 * THE CENTRE'S NAME SPEAKS FOR ITSELF (D-073/D-075). Centres
                 * are already called "Civil status centre of Yaounde I", so
                 * prefixing produced "Sent to the civil status centre of Civil
                 * status centre of Yaounde I". Found by looking at the screen.
                 */
                'detail' => $demande->center
                    ? __('citizen.tracking.milestone_submitted_detail', ['centre' => $demande->center->name])
                    : __('citizen.tracking.milestone_submitted_detail_generic'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_checked'),
                'statut' => RequestStatus::UnderReview,
                'detail' => __('citizen.tracking.milestone_checked_detail'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_mayor'),
                'statut' => RequestStatus::AwaitingSignature,
                'detail' => __('citizen.tracking.milestone_mayor_detail'),
            ],
            [
                'titre' => __('citizen.tracking.milestone_available'),
                'statut' => RequestStatus::Signed,
                /*
                 * IN THE RIGHT TENSE (D-073). "You will be able to download
                 * your certificate" was showing under a step marked "done":
                 * the future under an accomplished fact. Present tense as soon
                 * as the certificate exists.
                 */
                'detail' => $demande->signature !== null
                    ? __('citizen.tracking.milestone_available_ready')
                    : __('citizen.tracking.milestone_available_pending'),
            ],
        ];

        // L'ordre vit sur l'enumeration : un statut ajoute sans rang y leve
        // une erreur a la source, plutot que de casser cet ecran (D-043).
        //
        // MAIS LE RANG NE SUFFIT PAS QUAND LE PARCOURS S'EST ARRETE. `signed`,
        // `rejected` et `cancelled` partagent le rang 4 : le parcours s'arrete
        // la, qu'il aboutisse ou non. S'en servir pour decider quels jalons
        // sont « terminés » revenait a annoncer au demandeur des etapes qui
        // n'ont jamais eu lieu — un brouillon annule affichait « Demande
        // envoyée ✓ », « Vérification par l'officier ✓ » et « Décision du
        // maire ✓ ». Mentir au demandeur sur l'instruction de son dossier est
        // grave, et c'est exactement ce que la frise faisait.
        //
        // Pour un parcours arrete, le point d'arret se lit donc dans le
        // JOURNAL D'AUDIT — la seule trace de ce qui s'est reellement passe —
        // et non dans un rang que trois etats se partagent.
        $dernierJalonTrace = 0;

        foreach ($jalons as $index => $jalon) {
            if ($transitions->get($jalon['statut']->value) !== null) {
                $dernierJalonTrace = $index + 1;
            }
        }

        $atteint = $statut->isStopped() ? $dernierJalonTrace : $statut->timelineRank();
        $frise = [];

        foreach ($jalons as $index => $jalon) {
            $rang = $index + 1;
            $trace = $transitions->get($jalon['statut']->value);

            $etat = match (true) {
                // Une trace d'audit prime sur tout raisonnement de rang :
                // c'est la preuve que l'etape a eu lieu.
                $trace !== null => 'fait',
                $statut->isStopped() && $rang > $atteint => 'arrete',
                $rang < $atteint => 'fait',
                $rang === $atteint && ! $statut->isStopped() => 'en_cours',
                default => 'a_venir',
            };

            $frise[] = [
                'titre' => $jalon['titre'],
                'etat' => $etat,
                /*
                 * UN JALON NON ATTEINT NE PROMET RIEN (D-075).
                 *
                 * Sur un dossier refuse, la frise affichait « Acte disponible
                 * — non atteint » et, dessous, « Vous pourrez télécharger
                 * votre acte ». Le demandeur venait d'etre refuse : il ne
                 * telechargera pas d'acte. Les etapes qui n'auront pas lieu
                 * se taisent plutot que d'annoncer un avenir qui n'existe pas.
                 */
                'detail' => $etat === 'arrete' ? '' : $jalon['detail'],
                'date' => $trace?->created_at?->translatedFormat('d F Y à H:i'),
            ];
        }

        return $frise;
    }
}
