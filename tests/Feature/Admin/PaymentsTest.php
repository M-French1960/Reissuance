<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\CivilStatusCenter;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\PaymentOutcome;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les encaissements vus par l'administration (D-095).
 *
 * LA MOITIE DE CES TESTS VERIFIE UNE ABSENCE, et c'est voulu. La maquette
 * `admin-payments.html` prevoyait une colonne « Numero » — le numero Mobile
 * Money du payeur — et un bouton de remboursement. Les deux ont ete ecartes
 * pour des raisons qui ne se lisent pas dans le rendu de la page : un
 * relecteur pressé pourrait les « retablir » en croyant completer un ecran
 * inachevé. Ces tests rendent ce retour en arriere impossible en silence.
 */
class PaymentsTest extends TestCase
{
    private User $admin;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $centre = CivilStatusCenter::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $citoyen = User::factory()->citizen()->create();

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
        ]);
    }

    /**
     * `status` N'EST PAS DANS $fillable, et `create()` l'ignore donc EN
     * SILENCE. Mon premier montage posait `'status' => Settled` dans le
     * tableau et obtenait un paiement `pending` sans le moindre avertissement.
     * On le force apres coup, explicitement.
     */
    private function paiement(array $attributs = []): Payment
    {
        $statut = $attributs['status'] ?? null;
        unset($attributs['status']);

        $paiement = Payment::create(array_merge([
            'request_id' => $this->demande->id,
            'initiated_by' => $this->demande->user_id,
            'amount_minor' => 1000,
            'currency' => 'XAF',
            'minor_unit' => 0,
            'provider' => 'fake',
            'operator' => 'orange_money',
            'provider_reference' => 'DEMO-TX-'.self::$compteur++,
            'idempotency_key' => 'cle-'.uniqid('', true),
            'payer_reference' => '+237600000001',
        ], $attributs));

        if ($statut !== null) {
            // UN DECLENCHEUR REFUSE TOUTE TRANSITION SANS LIGNE D'AUDIT, sur
            // les paiements comme sur les demandes. `forceFill` echoue donc en
            // base, et c'est tant mieux : on passe par le service, qui est le
            // seul chemin qui laisse une trace.
            foreach ($this->cheminVers($statut) as $etape) {
                $paiement = app(PaymentService::class)->apply(
                    $paiement,
                    new PaymentOutcome($etape, 'fake', $paiement->provider_reference),
                    $this->admin,
                );
            }
        }

        return $paiement->refresh();
    }

    private static int $compteur = 1;

    /**
     * La machine a etats n'accepte pas tous les sauts : un paiement passe par
     * « autorise » avant d'etre « acquitte ».
     *
     * @return list<PaymentStatus>
     */
    private function cheminVers(PaymentStatus $vers): array
    {
        return match ($vers) {
            PaymentStatus::Settled => [PaymentStatus::Authorised, PaymentStatus::Settled],
            PaymentStatus::Refunded => [PaymentStatus::Authorised, PaymentStatus::Settled, PaymentStatus::Refunded],
            default => [$vers],
        };
    }

    #[Test]
    public function l_administrateur_lit_les_encaissements(): void
    {
        $this->paiement(['provider_reference' => 'DEMO-TX-1']);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('DEMO-TX-1')
            ->assertSee('Orange Money');
    }

    /**
     * LE NUMERO DU PAYEUR NE SORT PAS. C'est une donnee personnelle, et le
     * rapprochement financier n'en depend pas : la reference de l'operateur
     * identifie la transaction sans identifier personne.
     */
    #[Test]
    public function l_ecran_ne_montre_jamais_le_numero_du_payeur(): void
    {
        $this->paiement(['payer_reference' => '+237600000001']);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertDontSee('+237600000001')
            ->assertDontSee('600000001');
    }

    /**
     * LA REFERENCE DU DOSSIER NON PLUS. « L'administrateur ne voit AUCUNE
     * demande » est decrit dans RequestVisibilityScope comme le point le plus
     * important de la matrice ; cet ecran ne devait pas y faire exception au
     * nom du confort de lecture.
     */
    #[Test]
    public function l_ecran_ne_montre_jamais_la_reference_du_dossier(): void
    {
        $this->paiement();

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertDontSee($this->demande->reference);
    }

    #[Test]
    public function aucune_route_de_remboursement_n_existe(): void
    {
        $noms = collect(app('router')->getRoutes())
            ->map(fn ($r) => (string) $r->getName())
            ->filter(fn ($n) => str_contains($n, 'refund') || str_contains($n, 'rembours'));

        $this->assertTrue(
            $noms->isEmpty(),
            'Une route de remboursement est apparue. Le prestataire est factice : '
            ."elle annoncerait un virement qui ne partirait pas. Voir D-095.\n"
            .$noms->implode(', ')
        );
    }

    #[Test]
    public function les_filtres_restreignent_la_liste(): void
    {
        $this->paiement(['provider_reference' => 'DEMO-PAYE', 'status' => PaymentStatus::Settled]);
        $this->paiement(['provider_reference' => 'DEMO-ECHOUE', 'status' => PaymentStatus::Failed, 'idempotency_key' => 'cle-2']);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index', ['statut' => 'settled']))
            ->assertOk()
            ->assertSee('DEMO-PAYE')
            ->assertDontSee('DEMO-ECHOUE');
    }

    /** Un filtre inconnu ne doit pas faire lever, ni tout laisser passer en silence. */
    #[Test]
    public function un_filtre_inconnu_est_ignore_sans_erreur(): void
    {
        $this->paiement(['provider_reference' => 'DEMO-TX-LIBRE']);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index', ['statut' => 'inexistant', 'operateur' => 'bitcoin']))
            ->assertOk()
            ->assertSee('DEMO-TX-LIBRE');
    }

    #[Test]
    public function l_export_rend_un_csv_sans_donnee_personnelle(): void
    {
        $this->paiement(['provider_reference' => 'DEMO-TX-1']);

        $reponse = $this->actingAs($this->admin)->get(route('admin.payments.export'));

        $reponse->assertOk();
        $corps = $reponse->streamedContent();

        $this->assertStringContainsString('DEMO-TX-1', $corps);
        $this->assertStringContainsString('reference,operator,amount', $corps);
        $this->assertStringNotContainsString('+237600000001', $corps);
        $this->assertStringNotContainsString($this->demande->reference, $corps);
    }

    /**
     * L'INJECTION DE FORMULE. `provider_reference` vient de l'operateur, donc
     * de l'exterieur. Une cellule commencant par = est executee comme une
     * formule a l'ouverture du fichier dans un tableur.
     */
    #[Test]
    public function l_export_neutralise_une_formule_de_tableur(): void
    {
        $this->paiement(['provider_reference' => '=HYPERLINK("http://exemple.test")']);

        $corps = $this->actingAs($this->admin)
            ->get(route('admin.payments.export'))
            ->streamedContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $corps);
        $this->assertStringContainsString("'=HYPERLINK", $corps);
    }

    /** Ce qui part dans un fichier n'est plus protege par aucune Policy. */
    #[Test]
    public function l_export_est_inscrit_au_journal(): void
    {
        $this->paiement();

        $this->actingAs($this->admin)->get(route('admin.payments.export'))->streamedContent();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'payments.exported',
        ]);
    }

    #[Test]
    public function le_montant_de_l_export_est_lisible_par_une_machine(): void
    {
        $this->paiement(['amount_minor' => 1500]);

        $corps = $this->actingAs($this->admin)
            ->get(route('admin.payments.export'))
            ->streamedContent();

        // Ni separateur de milliers, ni virgule decimale : « 1 500 » n'est
        // plus un nombre pour un tableur, et une virgule couperait la cellule.
        $this->assertStringContainsString(',1500,XAF,', $corps);
    }

    /** @return iterable<string, array{string}> */
    public static function autresRoles(): iterable
    {
        yield 'citoyen' => ['citizen'];
        yield 'officier' => ['officer'];
        yield 'maire' => ['mayor'];
    }

    #[Test]
    public function seul_l_administrateur_accede_a_l_ecran(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        foreach ([
            User::factory()->citizen()->create(),
            User::factory()->officer($centre)->create(),
            User::factory()->mayor($centre->commune)->create(),
        ] as $intrus) {
            $this->actingAs($intrus)->get(route('admin.payments.index'))->assertForbidden();
            $this->actingAs($intrus)->get(route('admin.payments.export'))->assertForbidden();
        }

        // `actingAs` persiste d'un appel a l'autre : sans cette ligne, le cas
        // « anonyme » se jouait encore sous le maire et repondait 403.
        auth()->forgetUser();
        $this->flushSession();

        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function les_totaux_refusent_d_additionner_des_devises_differentes(): void
    {
        $this->paiement(['amount_minor' => 1000, 'currency' => 'XAF', 'status' => PaymentStatus::Settled]);
        $this->paiement([
            'amount_minor' => 500, 'currency' => 'EUR', 'minor_unit' => 2,
            'status' => PaymentStatus::Settled,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            // Additionner 1000 XAF et 5,00 EUR donnerait un nombre faux ; on
            // prefere ne rien afficher.
            ->assertSee('Several currencies');
    }

    #[Test]
    public function l_ecran_dit_pourquoi_le_remboursement_n_est_pas_offert(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('D9')
            ->assertSee('demonstration one', false);
    }
}
