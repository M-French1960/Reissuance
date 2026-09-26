<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentOperator;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Les encaissements, vus par l'administration (D-095).
 *
 * TROIS CHOSES QUE CET ECRAN NE FAIT PAS, ET C'EST LE FOND DE SA CONCEPTION.
 *
 * 1. IL NE MONTRE AUCUN NUMERO DE PAYEUR. La maquette prevoyait une colonne
 *    « Numero ». C'est le numero Mobile Money du citoyen, donc une donnee
 *    personnelle, et rien dans le rapprochement financier n'en depend : la
 *    reference rendue par l'operateur identifie la transaction sans identifier
 *    personne. `payer_reference` et `provider_payload` sont donc hors de la
 *    liste blanche de colonnes, comme le journal d'audit ferme la sienne.
 *
 * 2. IL NE MONTRE AUCUNE DEMANDE. « L'administrateur ne voit AUCUNE demande.
 *    Il gere les comptes, pas les dossiers d'identite » — c'est ecrit dans
 *    RequestVisibilityScope comme le point le plus important de la matrice.
 *    Rattacher ici la reference du dossier aurait demande un QUATRIEME
 *    contournement de portee, pour un confort de lecture. L'unite de cet ecran
 *    est donc le PAIEMENT, pas le dossier paye.
 *
 * 3. IL N'OFFRE PAS DE REMBOURSER. La maquette portait un bouton et un
 *    « TODO : POST /api/admin/refunds ». Le prestataire de paiement est un
 *    adaptateur factice : ce bouton annoncerait qu'un remboursement est parti
 *    alors que rien ne serait parti. C'est exactement le defaut que j'ai
 *    refuse sur le formulaire de contact de `help.html`. Les remboursements
 *    DEJA enregistres sont donc listes — c'est un fait —, mais en declencher
 *    un n'est pas propose, et l'ecran dit pourquoi.
 */
class PaymentController extends Controller
{
    /**
     * Colonnes exposees. Liste fermee, pour la meme raison que celle du
     * journal d'audit : une colonne ajoutee sans y penser est une fuite.
     *
     * @var list<string>
     */
    private const SAFE_COLUMNS = [
        'id', 'provider', 'operator', 'provider_reference',
        'amount_minor', 'currency', 'minor_unit', 'status',
        'created_at', 'settled_at', 'failed_at', 'refunded_at',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewPayments', User::class);

        return view('admin.payments.index', [
            'paiements' => $this->filtrer($request)->latest('created_at')->paginate(50)->withQueryString(),
            'totaux' => $this->totaux(),
            'operateurs' => PaymentOperator::cases(),
            'statuts' => PaymentStatus::cases(),
            'filtres' => [
                'operateur' => (string) $request->input('operateur', ''),
                'statut' => (string) $request->input('statut', ''),
                'reference' => (string) $request->input('reference', ''),
            ],
        ]);
    }

    /**
     * L'export, et pourquoi il est journalise.
     *
     * Un export sort la donnee du systeme : ce qui part dans un fichier
     * n'est plus protege par aucune Policy. L'ecran, lui, se relit ; le
     * fichier se transfere. Savoir qui a exporte, et quand, coute une ligne —
     * meme raisonnement qu'en D-060 pour la consultation des reglages.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewPayments', User::class);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role->value,
            'action' => 'payments.exported',
            'ip_address' => $request->ip(),
        ]);

        $lignes = $this->filtrer($request)->orderBy('created_at')->cursor();

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'wb');

            fputcsv($sortie, ['reference', 'operator', 'amount', 'currency', 'status', 'created_at', 'settled_at']);

            foreach ($lignes as $paiement) {
                fputcsv($sortie, [
                    self::neutraliser($paiement->provider_reference),
                    $paiement->operator?->value,
                    // Le montant en unite majeure, sous forme decimale : un
                    // tableur qui lit « 1000 » sans savoir que l'unite mineure
                    // vaut zero afficherait dix francs pour mille.
                    $paiement->money()->toMachineString(),
                    $paiement->currency,
                    $paiement->status->value,
                    $paiement->created_at?->toIso8601String(),
                    $paiement->settled_at?->toIso8601String(),
                ]);
            }

            fclose($sortie);
        }, 'phoenix-encaissements-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * NEUTRALISE L'INJECTION DE FORMULE.
     *
     * `provider_reference` vient de l'operateur, donc de l'exterieur. Une
     * cellule commencant par =, +, - ou @ est executee comme une FORMULE a
     * l'ouverture du fichier dans un tableur, et certaines permettent de
     * joindre un serveur distant. Le prefixe d'une apostrophe force la
     * lecture en texte.
     */
    private static function neutraliser(?string $valeur): string
    {
        $valeur = (string) $valeur;

        return $valeur !== '' && str_contains("=+-@\t\r", $valeur[0]) ? "'".$valeur : $valeur;
    }

    /** @return Builder<Payment> */
    private function filtrer(Request $request): Builder
    {
        return Payment::query()
            ->select(self::SAFE_COLUMNS)
            ->when(
                $request->filled('operateur') && PaymentOperator::tryFrom((string) $request->input('operateur')),
                fn ($q) => $q->where('operator', $request->input('operateur'))
            )
            ->when(
                $request->filled('statut') && PaymentStatus::tryFrom((string) $request->input('statut')),
                fn ($q) => $q->where('status', $request->input('statut'))
            )
            ->when(
                $request->filled('reference'),
                fn ($q) => $q->where('provider_reference', 'like', '%'.$request->input('reference').'%')
            );
    }

    /**
     * Les totaux par etat.
     *
     * Additionner des montants d'unites mineures differentes donnerait un
     * nombre faux ; on groupe donc aussi par devise, et on ne rend un total
     * que la ou il a un sens.
     *
     * @return list<array{statut: PaymentStatus, nombre: int, montant: Money|null}>
     */
    private function totaux(): array
    {
        $lignes = Payment::query()
            ->selectRaw('status, currency, minor_unit, count(*) as nombre, sum(amount_minor) as total')
            ->groupBy('status', 'currency', 'minor_unit')
            ->get();

        $parStatut = [];

        foreach (PaymentStatus::cases() as $statut) {
            $correspondantes = $lignes->where('status', $statut);

            if ($correspondantes->isEmpty()) {
                continue;
            }

            $devises = $correspondantes->unique(fn ($l) => $l->currency.'/'.$l->minor_unit);
            $premiere = $correspondantes->first();

            $parStatut[] = [
                'statut' => $statut,
                'nombre' => (int) $correspondantes->sum('nombre'),
                // Plusieurs devises melangees : aucun total n'est juste, donc
                // on n'en affiche aucun plutot qu'un nombre faux.
                'montant' => $devises->count() === 1
                    ? new Money((int) $correspondantes->sum('total'), $premiere->currency, (int) $premiere->minor_unit)
                    : null,
            ];
        }

        return $parStatut;
    }
}
