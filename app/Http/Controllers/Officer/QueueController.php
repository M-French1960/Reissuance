<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * File de traitement de l'officier.
 *
 * C'est un poste de travail a volume : on optimise pour la repetition
 * (8.2 du brief). Filtres, tri, pagination serveur.
 *
 * La portee globale restreint deja aux demandes du centre de l'officier et
 * exclut les brouillons : ce controleur n'a aucun `where` de securite a poser,
 * et un oubli ici ne peut pas faire fuir une donnee hors perimetre.
 */
class QueueController extends Controller
{
    private const SORTABLE = ['submitted_at', 'reference', 'status'];

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', ReissuanceRequest::class);

        $sort = in_array($request->input('tri'), self::SORTABLE, true)
            ? $request->input('tri')
            : 'submitted_at';

        $direction = $request->input('sens') === 'asc' ? 'asc' : 'desc';

        $requests = ReissuanceRequest::query()
            ->with(['citizen:id,name', 'assignedOfficer:id,name'])
            ->when($request->filled('statut'), fn ($q) => $q->where('status', $request->input('statut')))
            ->when($request->filled('recherche'), function ($q) use ($request): void {
                $terme = trim((string) $request->input('recherche'));
                $q->where(function ($sub) use ($terme): void {
                    $sub->where('reference', 'ilike', "%{$terme}%")
                        ->orWhere('full_name_at_birth', 'ilike', "%{$terme}%");
                });
            })
            ->when($request->filled('depuis'), fn ($q) => $q->where('submitted_at', '>=', $request->date('depuis')))
            ->when($request->input('assignation') === 'moi',
                fn ($q) => $q->where('assigned_officer_id', $request->user()->id))
            ->when($request->input('assignation') === 'libre',
                fn ($q) => $q->whereNull('assigned_officer_id'))
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            // Pagination systematique : aucune collection non bornee (8.5).
            ->paginate(25)
            ->withQueryString();

        return view('officer.queue', [
            'requests' => $requests,
            'counts' => $this->counts(),
            'sort' => $sort,
            'direction' => $direction,
            // Les brouillons n'existent que pour leur auteur : ils ne figurent
            // dans aucun filtre de l'officier.
            'statuses' => array_filter(
                RequestStatus::cases(),
                fn (RequestStatus $s): bool => $s !== RequestStatus::Draft,
            ),
        ]);
    }

    /** @return array<string, int> */
    private function counts(): array
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
