<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Support\Tracking\RequestTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aiguillage vers le tableau de bord du role.
 *
 * Les comptages passent par ReissuanceRequest, donc par la portee globale :
 * un officier ne peut pas compter les demandes d'un autre centre, meme si
 * cette requete-ci oubliait un where.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly RequestTimeline $frise) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Citizen => view('dashboard.citizen', $this->citizen($user)),

            UserRole::Officer => view('dashboard.officer', [
                'counts' => $this->countsByStatus(),
            ]),

            UserRole::Mayor => view('dashboard.mayor', [
                'counts' => $this->countsByStatus(),
            ]),

            UserRole::Admin => view('dashboard.admin', [
                'accounts' => User::query()
                    ->selectRaw('role, status, count(*) as total')
                    ->groupBy('role', 'status')
                    ->get(),
            ]),
        };
    }

    /**
     * Ce que le demandeur voit en arrivant (D-079).
     *
     * TOUT PART DE ReissuanceRequest, donc de la portee globale : meme si une
     * de ces requetes oubliait un `where`, un citoyen ne compterait pas les
     * demandes d'un autre.
     *
     * @return array<string, mixed>
     */
    private function citizen(User $user): array
    {
        $demandes = ReissuanceRequest::query()->with('center:id,name')->latest('id')->get();

        /*
         * DEUX COMPTEURS, ET LE SECOND NE DIT PAS « TERMINEES ».
         *
         * La maquette proposait « Demandes en cours » et « Demandes
         * terminees ». Le piege est que trois etats terminent un parcours :
         * `signed`, `rejected` et `cancelled`. Compter les trois ensemble
         * ferait lire « 1 demande terminee » a quelqu'un dont la demande a ete
         * REFUSEE. Le second compteur ne compte donc que les actes reellement
         * delivres, et son libelle le dit.
         *
         * `isStopped()` ne convient pas ici : il ne couvre que le refus et
         * l'annulation, pas la signature. Les etats sont donc nommes.
         *
         * Un brouillon compte comme « en cours » : il est commence et il
         * attend son auteur. Sans cela, quelqu'un qui a laisse une demande a
         * mi-chemin lirait « 0 en cours » et croirait n'avoir rien a faire.
         */
        $arretes = [RequestStatus::Signed, RequestStatus::Rejected, RequestStatus::Cancelled];

        $enCours = $demandes->reject(
            fn (ReissuanceRequest $d): bool => in_array($d->status, $arretes, true)
        );

        $delivres = $demandes->filter(
            fn (ReissuanceRequest $d): bool => $d->status === RequestStatus::Signed
        );

        /*
         * La demande suivie en haut de page : la plus recente qui vit encore
         * ET qui a ete envoyee. Un brouillon n'a pas de frise a montrer, il a
         * un formulaire a terminer.
         */
        $suivie = $enCours->first(fn (ReissuanceRequest $d): bool => $d->status !== RequestStatus::Draft);

        return [
            'requests' => $demandes->take(10),
            'enCours' => $enCours->count(),
            'delivres' => $delivres->count(),
            'brouillon' => $enCours->first(fn (ReissuanceRequest $d): bool => $d->status === RequestStatus::Draft),
            'suivie' => $suivie,
            'frise' => $suivie === null ? [] : $this->frise->for($suivie),

            /*
             * L'activite recente, ce sont SES notifications. Le journal
             * d'audit ne lui est pas accessible, et c'est voulu : il porte
             * les noms des agents et les motifs internes.
             */
            'activite' => $user->notifications()->latest()->limit(5)->get(),

            /*
             * Les paiements passent par les demandes du citoyen, donc par la
             * portee globale. `whereIn` sur des identifiants deja filtres
             * plutot qu'une jointure libre : c'est la portee qui decide du
             * perimetre, pas cette requete.
             */
            'paiements' => Payment::query()
                ->whereIn('request_id', $demandes->pluck('id'))
                ->with('request:id,reference')
                ->latest('id')
                ->limit(5)
                ->get(),

            /*
             * Le depot exige un profil complet. L'ecran le DIT (D-073)
             * plutot que de laisser le citoyen s'y heurter a l'etape
             * suivante.
             */
            'profilComplet' => $user->profile?->completed_at !== null,
        ];
    }

    /** @return array<string, int> */
    private function countsByStatus(): array
    {
        $counts = ReissuanceRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (RequestStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }
}
