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

        $ordre = [
            RequestStatus::Draft->value => 0,
            RequestStatus::Pending->value => 1,
            RequestStatus::UnderReview->value => 2,
            RequestStatus::Escalated->value => 2,
            RequestStatus::AwaitingSignature->value => 3,
            RequestStatus::Signed->value => 4,
            RequestStatus::Rejected->value => 4,
        ];

        $atteint = $ordre[$statut->value];
        $frise = [];

        foreach ($jalons as $index => $jalon) {
            $rang = $index + 1;
            $trace = $transitions->get($jalon['statut']->value);

            $frise[] = [
                'titre' => $jalon['titre'],
                'etat' => match (true) {
                    $statut === RequestStatus::Rejected && $rang > $atteint - 1 => 'arrete',
                    $rang < $atteint || $trace !== null => 'fait',
                    $rang === $atteint => 'en_cours',
                    default => 'a_venir',
                },
                'detail' => $jalon['detail'],
                'date' => $trace?->created_at?->translatedFormat('d F Y à H:i'),
            ];
        }

        return $frise;
    }
}
