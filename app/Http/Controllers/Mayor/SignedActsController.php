<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mayor;

use App\Enums\DecisionType;
use App\Http\Controllers\Controller;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Ce que vous avez signe » (D-086).
 *
 * POURQUOI CET ECRAN A DEMANDE UNE DECISION AVANT UNE LIGNE DE CODE. Une fois
 * signee, une demande sortait entierement de la vue du maire : sa portee ne
 * couvrait que « en attente de signature » et « escaladee ». Le signataire ne
 * pouvait donc plus savoir ce qu'il avait signe, ni relire l'acte qui porte sa
 * signature. C'est intenable pour la personne qui en repond.
 *
 * La portee s'ouvre donc a l'etat « signe », strictement limite aux demandes
 * dont la signature est enregistree a SON nom. Un adjoint de la meme commune
 * ne voit pas l'acte signe par son collegue. Ce qui ne s'ouvre PAS : aucune
 * action — `sign` et `returnToOfficer` exigent toujours les deux etats de
 * competence — et la piece d'identite du citoyen, qui se ferme a la signature.
 *
 * CE QUE CET ECRAN NE MONTRE PAS, ET POURQUOI.
 *
 * 1. LA DELIVRANCE DES COPIES. La maquette porte une colonne « A retirer /
 *    Delivree ». Cette notion n'existe pas : rien dans le modele de donnees ne
 *    suit un retrait de copie, et le service delivre un acte electronique.
 *    L'inventer aurait affiche un etat faux a cote d'un acte signe.
 * 2. UN EXPORT CSV. La maquette propose d'exporter la liste, noms des
 *    titulaires compris. Un export nominatif est une donnee personnelle qui
 *    SORT de la plateforme, sans journal ni destinataire connu. Cela demande
 *    un arbitrage, pas un bouton.
 * 3. UN DELAI MOYEN « verification vers signature » presente comme un
 *    indicateur de performance. Le delai est calcule et affiche, mais comme un
 *    fait mesure sur la periode — jamais compare a une cible, parce qu'aucun
 *    delai de traitement n'est arbitre (question ouverte D8).
 */
class SignedActsController extends Controller
{
    /** Les periodes proposees, en semaines. Rien d'autre n'est accepte. */
    private const PERIODES = [4, 8, 12];

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', ReissuanceRequest::class);

        $semaines = in_array((int) $request->input('semaines'), self::PERIODES, true)
            ? (int) $request->input('semaines')
            : self::PERIODES[1];

        $maire = $request->user();
        $debut = now()->startOfWeek()->subWeeks($semaines - 1);

        return view('mayor.signed', [
            'semaines' => $semaines,
            'periodes' => self::PERIODES,
            'debut' => $debut,
            'series' => $this->signaturesParSemaine($maire->id, $debut, $semaines),
            'signatures' => $this->historique($maire->id, $request),
            'total' => DocumentSignature::where('mayor_id', $maire->id)
                ->where('signed_at', '>=', $debut)->count(),
            'retournees' => $this->decisionsDuMaire($maire->id, $debut, DecisionType::Returned),
            'delaiMoyen' => $this->delaiMoyenEnJours($maire->id, $debut),
        ]);
    }

    /**
     * Les signatures de ce maire, semaine par semaine.
     *
     * Le regroupement se fait en base (8.5 : aucune collection non bornee).
     *
     * @return list<array{debut: CarbonInterface, total: int}>
     */
    private function signaturesParSemaine(int $maire, CarbonInterface $debut, int $semaines): array
    {
        $lignes = DocumentSignature::query()
            ->where('mayor_id', $maire)
            ->where('signed_at', '>=', $debut)
            // Mode 3 : semaines ISO, lundi au dimanche, comme le reste du
            // service.
            ->selectRaw('YEARWEEK(signed_at, 3) as semaine, count(*) as total')
            ->groupBy('semaine')
            ->pluck('total', 'semaine');

        $serie = [];

        for ($i = 0; $i < $semaines; $i++) {
            $jour = $debut->copy()->addWeeks($i);

            $serie[] = [
                'debut' => $jour,
                // Une semaine sans signature reste dans la serie : un trou est
                // une information.
                'total' => (int) ($lignes[(int) $jour->isoFormat('GGGGWW')] ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * L'historique lui-meme, pagine et filtrable.
     *
     * La recherche porte sur la reference et sur le nom de naissance : ce sont
     * les deux facons dont on retrouve un acte qu'on a signe. Le maire a vu ce
     * nom au moment de signer ; l'afficher ici n'expose rien de nouveau.
     */
    private function historique(int $maire, Request $request): mixed
    {
        return DocumentSignature::query()
            ->where('mayor_id', $maire)
            ->with(['request' => fn ($q) => $q->with('center:id,name')])
            ->when($request->filled('recherche'), function ($query) use ($request): void {
                $terme = trim((string) $request->input('recherche'));
                $query->whereHas('request', function ($q) use ($terme): void {
                    $q->where('reference', 'like', "%{$terme}%")
                        ->orWhere('full_name_at_birth', 'like', "%{$terme}%");
                });
            })
            ->latest('signed_at')
            ->paginate(20)
            ->withQueryString();
    }

    /** Combien de dossiers ce maire a-t-il renvoyes a l'officier sur la periode ? */
    private function decisionsDuMaire(int $maire, CarbonInterface $debut, DecisionType $type): int
    {
        return RequestDecision::query()
            ->where('actor_id', $maire)
            ->where('decision', $type->value)
            ->where('created_at', '>=', $debut)
            ->count();
    }

    /**
     * Le delai moyen entre la mise en attente de signature et la signature.
     *
     * C'EST UNE MESURE, PAS UN INDICATEUR. Aucune cible ne lui est opposee :
     * le delai de traitement opposable est la question ouverte D8, et comparer
     * ce nombre a un seuil que j'aurais choisi en ferait un jugement.
     *
     * Le calcul se fait en base, sur les signatures de la periode, en joignant
     * la decision qui a mis le dossier en attente de signature.
     */
    private function delaiMoyenEnJours(int $maire, CarbonInterface $debut): ?float
    {
        $moyenne = DocumentSignature::query()
            ->where('document_signatures.mayor_id', $maire)
            ->where('document_signatures.signed_at', '>=', $debut)
            ->join('request_decisions', function ($jointure): void {
                $jointure->on('request_decisions.request_id', '=', 'document_signatures.request_id')
                    ->where('request_decisions.decision', '=', DecisionType::Accepted->value);
            })
            ->selectRaw(
                'avg(timestampdiff(hour, request_decisions.created_at, document_signatures.signed_at)) as heures'
            )
            ->value('heures');

        // Aucune signature sur la periode, ou aucune decision correspondante :
        // on ne rend pas zero, qui se lirait comme « instantane ».
        return $moyenne === null ? null : round((float) $moyenne / 24, 1);
    }
}
