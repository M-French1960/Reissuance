<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCivilStatusCenterRequest;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Le raccordement des centres d'etat civil (D-085).
 *
 * CE QUE CET ECRAN FAIT. Un centre d'etat civil n'existe pas parce que
 * quelqu'un a clique dans une interface : il existe par un acte administratif
 * qui ne passe pas par cette plateforme. Ce qui se decide ici, c'est de
 * RACCORDER un centre existant — lui permettre de recevoir les demandes des
 * citoyens — ou de le debrancher. Le vocabulaire de l'ecran le dit, parce que
 * « creer un centre » serait faux (§10).
 *
 * CE QUE « RACCORDE » CHANGE, EXACTEMENT. Le drapeau `is_active` est lu a un
 * seul endroit : la liste des centres que le citoyen peut choisir dans son
 * formulaire. Debrancher un centre l'empeche donc de recevoir de NOUVELLES
 * demandes, et ne touche a AUCUNE demande en cours — celles-la restent
 * traitables par les agents du centre jusqu'a leur terme. L'ecran l'ecrit,
 * plutot que de laisser l'administrateur le deviner.
 *
 * CE QUE L'ADMINISTRATEUR VOIT DES DEMANDES, ET CE QU'IL N'EN VOIT PAS. Il ne
 * voit AUCUNE demande : la portee globale le lui interdit, et c'est la regle
 * la plus importante de la matrice (4.2 du brief). Cet ecran affiche des
 * AGREGATS par centre — combien de dossiers y attendent, depuis quand le plus
 * ancien — obtenus par `ReissuanceRequest::aggregatesForAdministration()`, qui
 * ne rend aucune colonne d'une demande. Decider s'il faut un agent de plus a
 * Yaounde III demande un nombre, pas une liste de noms.
 *
 * AUCUNE SUPPRESSION. Il n'existe pas de route de suppression : un centre
 * supprime rendrait orphelines des demandes envoyees, des decisions signees et
 * des lignes d'audit. Debrancher est la seule operation, et elle est
 * reversible.
 */
class CenterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CivilStatusCenter::class);

        $centres = CivilStatusCenter::query()
            ->with('commune:id,name')
            ->when($request->filled('recherche'), function ($query) use ($request): void {
                $terme = trim((string) $request->input('recherche'));
                $query->where(function ($q) use ($terme): void {
                    $q->where('name', 'like', "%{$terme}%")
                        ->orWhere('city', 'like', "%{$terme}%")
                        ->orWhere('code', 'like', "%{$terme}%")
                        ->orWhereHas('commune', fn ($c) => $c->where('name', 'like', "%{$terme}%"));
                });
            })
            ->when($request->input('raccordement') !== null && $request->input('raccordement') !== '',
                fn ($q) => $q->where('is_active', $request->input('raccordement') === 'connected'))
            ->orderBy('name')
            // Pagination systematique : aucune collection non bornee (8.5).
            ->paginate(12)
            ->withQueryString();

        return view('admin.centers.index', [
            'centers' => $centres,
            'aggregates' => self::aggregates($centres->getCollection()),
            'officers' => self::officerCounts($centres->getCollection()),
            'mayors' => self::mayorCounts($centres->getCollection()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CivilStatusCenter::class);

        return view('admin.centers.create', [
            'communes' => Commune::orderBy('name')->get(),
        ]);
    }

    public function store(SaveCivilStatusCenterRequest $request): RedirectResponse
    {
        $donnees = $request->validated();

        $centre = DB::transaction(function () use ($donnees, $request): CivilStatusCenter {
            $centre = CivilStatusCenter::create($donnees);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role->value,
                'action' => 'center.connected',
                'auditable_type' => 'civil_status_center',
                'auditable_id' => $centre->id,
                'reason' => $centre->code,
                'ip_address' => $request->ip(),
            ]);

            return $centre;
        });

        return redirect()
            ->route('admin.centers.index')
            ->with('status', __('admin.centers.created', ['name' => $centre->name]));
    }

    public function edit(CivilStatusCenter $center): View
    {
        $this->authorize('update', $center);

        return view('admin.centers.edit', [
            'center' => $center,
            'communes' => Commune::orderBy('name')->get(),
            // Le code ne se corrige que sur un centre encore vierge : la Policy
            // tranche, sur le nombre que la methode auditee a rendu.
            'received' => $recu = self::receivedCount($center),
            'codeEditable' => Gate::allows('changeCode', [$center, $recu]),
        ]);
    }

    public function update(SaveCivilStatusCenterRequest $request, CivilStatusCenter $center): RedirectResponse
    {
        $donnees = $request->validated();
        $recu = self::receivedCount($center);

        /*
         * LE CODE EST REFUSE, PAS IGNORE. Un champ desactive dans le
         * formulaire n'est pas une protection : il suffit de le reactiver dans
         * le navigateur. La regle est donc appliquee ici, et un refus est
         * annonce — un code silencieusement rejete ferait croire a une
         * modification qui n'a pas eu lieu.
         */
        if ($donnees['code'] !== $center->code && Gate::denies('changeCode', [$center, $recu])) {
            return back()
                ->withInput()
                ->withErrors(['code' => __('admin.centers.code_frozen', ['count' => $recu])]);
        }

        DB::transaction(function () use ($center, $donnees, $request): void {
            $avant = $center->is_active;
            $center->fill($donnees)->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role->value,
                'action' => $avant === $center->is_active
                    ? 'center.updated'
                    : ($center->is_active ? 'center.connected' : 'center.disconnected'),
                'auditable_type' => 'civil_status_center',
                'auditable_id' => $center->id,
                'reason' => $center->code,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('admin.centers.index')
            ->with('status', __('admin.centers.updated', ['name' => $center->name]));
    }

    /**
     * Les agregats par centre, indexes par identifiant de centre.
     *
     * Une seule requete pour toute la page : la boucle de la vue lit un
     * tableau deja constitue, elle n'interroge rien.
     *
     * @param  Collection<int, CivilStatusCenter>  $centres
     * @return array<int, object{received: int, in_flight: int, oldest: ?string}>
     */
    private static function aggregates(Collection $centres): array
    {
        if ($centres->isEmpty()) {
            return [];
        }

        return ReissuanceRequest::aggregatesForAdministration()
            ->whereIn('civil_status_center_id', $centres->pluck('id'))
            ->get()
            ->keyBy('civil_status_center_id')
            ->map(fn ($ligne): object => (object) [
                'received' => (int) $ligne->received,
                'in_flight' => (int) $ligne->in_flight,
                'oldest' => $ligne->oldest,
            ])
            ->all();
    }

    /**
     * Combien de dossiers ce centre a-t-il deja recus ?
     *
     * Le nombre vient de la methode auditee : la Policy ne compte pas
     * elle-meme, parce que compter exige de contourner la portee globale et
     * que ce contournement vit a un seul endroit.
     */
    private static function receivedCount(CivilStatusCenter $center): int
    {
        $ligne = ReissuanceRequest::aggregatesForAdministration()
            ->where('civil_status_center_id', $center->id)
            ->first();

        return (int) ($ligne?->received ?? 0);
    }

    /**
     * Les agents ACTIFS par centre.
     *
     * Un compte suspendu ou desactive ne traite rien : le compter ferait
     * croire un centre dote alors qu'il ne l'est pas.
     *
     * @param  Collection<int, CivilStatusCenter>  $centres
     * @return array<int, int>
     */
    private static function officerCounts(Collection $centres): array
    {
        if ($centres->isEmpty()) {
            return [];
        }

        return User::query()
            ->selectRaw('civil_status_center_id, count(*) as total')
            ->where('role', UserRole::Officer->value)
            ->where('status', 'active')
            ->whereIn('civil_status_center_id', $centres->pluck('id'))
            ->groupBy('civil_status_center_id')
            ->pluck('total', 'civil_status_center_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Les maires actifs par COMMUNE, pas par centre.
     *
     * C'est la commune qui determine quel maire est competent (voir la portee
     * globale), et un centre dont la commune n'a aucun maire actif produira
     * des actes que personne ne peut signer. L'ecran le dit avant que le
     * dossier d'un citoyen s'y arrete.
     *
     * @param  Collection<int, CivilStatusCenter>  $centres
     * @return array<int, int>
     */
    private static function mayorCounts(Collection $centres): array
    {
        if ($centres->isEmpty()) {
            return [];
        }

        return User::query()
            ->selectRaw('commune_id, count(*) as total')
            ->where('role', UserRole::Mayor->value)
            ->where('status', 'active')
            ->whereIn('commune_id', $centres->pluck('commune_id')->unique())
            ->groupBy('commune_id')
            ->pluck('total', 'commune_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
