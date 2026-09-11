<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

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
            'messages' => $reissuanceRequest->messages()->with('author:id,name')->oldest()->get(),
        ]);
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
                'titre' => 'Demande envoyée',
                'statut' => RequestStatus::Pending,
                'detail' => "Transmise au centre d'état civil".($demande->center ? " de {$demande->center->name}" : ''),
            ],
            [
                'titre' => "Vérification par l'officier",
                'statut' => RequestStatus::UnderReview,
                'detail' => "Contrôle de votre identité et recherche de l'acte d'origine.",
            ],
            [
                'titre' => 'Décision du maire',
                'statut' => RequestStatus::AwaitingSignature,
                'detail' => 'Signature de votre acte réédité.',
            ],
            [
                'titre' => 'Acte disponible',
                'statut' => RequestStatus::Signed,
                'detail' => 'Vous pourrez télécharger votre acte.',
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

            $frise[] = [
                'titre' => $jalon['titre'],
                'etat' => match (true) {
                    // Une trace d'audit prime sur tout raisonnement de rang :
                    // c'est la preuve que l'etape a eu lieu.
                    $trace !== null => 'fait',
                    $statut->isStopped() && $rang > $atteint => 'arrete',
                    $rang < $atteint => 'fait',
                    $rang === $atteint && ! $statut->isStopped() => 'en_cours',
                    default => 'a_venir',
                },
                'detail' => $jalon['detail'],
                'date' => $trace?->created_at?->translatedFormat('d F Y à H:i'),
            ];
        }

        return $frise;
    }
}
