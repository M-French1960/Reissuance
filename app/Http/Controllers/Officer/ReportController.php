<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Enums\DecisionType;
use App\Http\Controllers\Controller;
use App\Models\RequestDecision;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rapports d'activite de l'officier (D-084).
 *
 * « Votre activite de verification », et le mot VOTRE est tenu : le rapport ne
 * compte que les decisions prises par le compte connecte. Compter celles du
 * centre entier aurait fait d'un outil de travail personnel un instrument de
 * comparaison entre collegues, ce que personne n'a demande.
 *
 * LA MAQUETTE LE DISAIT ELLE-MEME : « Données d'exemple. En production,
 * calculées par le serveur à partir du journal d'audit. » La source retenue
 * n'est pas le journal mais `request_decisions`, qui porte la decision, son
 * motif et sa date. Le journal, lui, ne garde que la transition d'etat.
 */
class ReportController extends Controller
{
    /** Les periodes proposees, en semaines. Rien d'autre n'est accepte. */
    private const PERIODES = [4, 8, 12];

    public function __invoke(Request $request): View
    {
        $semaines = in_array((int) $request->input('semaines'), self::PERIODES, true)
            ? (int) $request->input('semaines')
            : self::PERIODES[1];

        $debut = now()->startOfWeek()->subWeeks($semaines - 1);

        return view('officer.reports', [
            'semaines' => $semaines,
            'periodes' => self::PERIODES,
            'debut' => $debut,
            'series' => $this->decisionsParSemaine($request->user()->id, $debut, $semaines),
            'motifs' => $this->motifsDeRejet($request->user()->id, $debut),
            'types' => $this->typesAffiches(),
        ]);
    }

    /**
     * Les decisions de cet officier, semaine par semaine.
     *
     * Le regroupement se fait en base : un officier peut avoir pris des
     * centaines de decisions sur douze semaines, et les ramener toutes pour
     * les compter en PHP serait une collection non bornee (8.5 du brief).
     *
     * @return list<array{debut: CarbonInterface, total: int, parType: array<string, int>}>
     */
    private function decisionsParSemaine(int $officier, CarbonInterface $debut, int $semaines): array
    {
        $lignes = RequestDecision::query()
            ->where('actor_id', $officier)
            ->where('created_at', '>=', $debut)
            // Mode 3 : semaines ISO, lundi au dimanche, comme le reste du
            // service. Le projet impose MySQL, la fonction est disponible.
            ->selectRaw('YEARWEEK(created_at, 3) as semaine, decision, count(*) as total')
            ->groupBy('semaine', 'decision')
            ->get();

        $parSemaine = [];

        foreach ($lignes as $ligne) {
            $parSemaine[(int) $ligne->semaine][(string) $ligne->decision->value] = (int) $ligne->total;
        }

        $serie = [];

        for ($i = 0; $i < $semaines; $i++) {
            $jour = $debut->copy()->addWeeks($i);
            $cle = (int) $jour->isoFormat('GGGGWW');
            $types = $parSemaine[$cle] ?? [];

            $serie[] = [
                'debut' => $jour,
                // Une semaine sans decision reste dans la serie : un trou est
                // une information, et le masquer ferait croire a une activite
                // continue.
                'total' => array_sum($types),
                'parType' => $types,
            ];
        }

        return $serie;
    }

    /**
     * Les motifs de rejet, et SEULEMENT CEUX QUI SONT PRE-REMPLIS.
     *
     * Le motif est du texte libre saisi par un agent. Il peut contenir le nom
     * d'une personne, un numero de piece, un detail de dossier. Les compter
     * tels quels ferait apparaitre des donnees personnelles dans un ecran de
     * statistiques, ou elles n'ont rien a faire.
     *
     * Seuls les motifs qui correspondent EXACTEMENT a l'un des motifs proposes
     * par l'interface sont donc nommes ; tout le reste est regroupe sous
     * « autre motif ». C'est aussi ce qui rend le comptage utile : un
     * regroupement par texte libre donnerait un seau par formulation.
     *
     * @return list<array{libelle: string, total: int, prerempli: bool}>
     */
    private function motifsDeRejet(int $officier, CarbonInterface $debut): array
    {
        $preremplis = DecisionController::rejectionReasons();

        $lignes = RequestDecision::query()
            ->where('actor_id', $officier)
            ->where('decision', DecisionType::Rejected->value)
            ->where('created_at', '>=', $debut)
            ->selectRaw('reason, count(*) as total')
            ->groupBy('reason')
            ->get();

        $comptes = [];
        $autres = 0;

        foreach ($lignes as $ligne) {
            $motif = trim((string) $ligne->reason);

            if (in_array($motif, $preremplis, true)) {
                $comptes[$motif] = ($comptes[$motif] ?? 0) + (int) $ligne->total;

                continue;
            }

            $autres += (int) $ligne->total;
        }

        arsort($comptes);

        $motifs = [];

        foreach ($comptes as $libelle => $total) {
            $motifs[] = ['libelle' => $libelle, 'total' => $total, 'prerempli' => true];
        }

        if ($autres > 0) {
            $motifs[] = ['libelle' => __('officer.reports.other_reason'), 'total' => $autres, 'prerempli' => false];
        }

        return $motifs;
    }

    /**
     * LES TROIS TYPES, DANS CET ORDRE, ET IL NE SE CHANGE PAS.
     *
     * L'ordre n'est pas logique, il est OPTIQUE. Les trois teintes ont ete
     * passees au validateur de palette : dans l'ordre « accepte, rejete,
     * escalade », l'ambre et le rouge se touchent dans la barre empilee et
     * leur ecart tombe a 14,1 — sous le plancher de 15, c'est-a-dire deux
     * couleurs qu'un lecteur ne distingue pas, meme avec une vision normale.
     *
     * Dans l'ordre ci-dessous, l'ambre et le rouge ne sont jamais voisins :
     * tous les controles passent, ecart le plus faible 18,1 en vision normale
     * et 8,8 en deuteranopie.
     *
     * Remettre cet ordre « dans le bon sens » casserait la lisibilite sans
     * qu'aucun test ne rougisse. Un test fige donc l'ordre.
     *
     * @return list<DecisionType>
     */
    public static function typesAffiches(): array
    {
        return [DecisionType::Rejected, DecisionType::Accepted, DecisionType::Escalated];
    }
}
