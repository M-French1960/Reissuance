<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\PaymentOperator;
use App\Models\CivilStatusCenter;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentGate;
use App\Services\PaymentReceipt;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * « Pay Through Orange Money » et « Pay Through Mobile Money » — les deux
 * specialisations de « Make Payment » au diagramme.
 *
 * Le choix appartient au demandeur, il est conserve avec l'encaissement, et il
 * figure au recu : un rapprochement comptable se fait par operateur.
 */
class PaymentOperatorTest extends TestCase
{
    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION,
            'phoenix.payments.amount_minor' => '1000',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
        ]);

        $centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-360000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
    }

    /** @return list<array{string, string}> */
    public static function operateurs(): array
    {
        return [
            'Orange Money' => ['orange_money', 'Orange Money'],
            'MTN Mobile Money' => ['mtn_mobile_money', 'MTN Mobile Money'],
        ];
    }

    #[Test]
    #[DataProvider('operateurs')]
    public function le_demandeur_choisit_son_operateur(string $valeur, string $libelle): void
    {
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $this->demande), [
                'operator' => $valeur,
                'payer_reference' => '+237600000001',
            ])->assertSessionHasNoErrors();

        $paiement = Payment::where('request_id', $this->demande->id)->firstOrFail();

        $this->assertSame($valeur, $paiement->operator->value);
        $this->assertSame($libelle, $paiement->operator->label());
    }

    #[Test]
    public function les_deux_operateurs_sont_proposes_a_l_ecran(): void
    {
        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.payment', $this->demande))
            ->assertOk()
            ->assertSee('Orange Money')
            ->assertSee('MTN Mobile Money');
    }

    #[Test]
    public function un_reglement_sans_operateur_est_refuse(): void
    {
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $this->demande), [
                'payer_reference' => '+237600000001',
            ])->assertSessionHasErrors('operator');

        $this->assertSame(0, Payment::count());
    }

    /** Un operateur inconnu est refuse : pas de repli silencieux. */
    #[Test]
    public function un_operateur_inconnu_est_refuse(): void
    {
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $this->demande), [
                'operator' => 'monnaie_inventee',
                'payer_reference' => '+237600000001',
            ])->assertSessionHasErrors('operator');

        $this->assertSame(0, Payment::count());
    }

    /** Et la base refuse aussi, si la validation etait contournee. */
    #[Test]
    public function la_base_refuse_un_operateur_inconnu(): void
    {
        $paiement = app(PaymentService::class)->initiate(
            $this->demande, $this->citoyen, '+237600000001', PaymentOperator::OrangeMoney
        );

        $this->expectException(QueryException::class);

        DB::table('payments')->where('id', $paiement->id)->update(['operator' => 'monnaie_inventee']);
    }

    #[Test]
    public function l_operateur_figure_au_recu(): void
    {
        $service = app(PaymentService::class);
        $paiement = $service->reconcile($service->initiate(
            $this->demande, $this->citoyen, '+237600000001', PaymentOperator::MtnMobileMoney
        ));

        $pdf = app(PaymentReceipt::class)->build($paiement->load('request.citizen.profile'));

        $chemin = tempnam(sys_get_temp_dir(), 'recu').'.pdf';
        file_put_contents($chemin, $pdf);

        try {
            exec('pdftotext -layout '.escapeshellarg($chemin).' - 2>/dev/null', $lignes);
            $this->assertStringContainsString('MTN Mobile Money', implode("\n", $lignes));
        } finally {
            @unlink($chemin);
        }
    }

    /**
     * Le rapprochement comptable se fait par operateur.
     *
     * C'est la raison d'etre de la colonne : sans elle, on ne saurait pas quel
     * operateur doit quelle somme.
     */
    #[Test]
    public function les_encaissements_se_comptent_par_operateur(): void
    {
        $service = app(PaymentService::class);

        foreach ([
            [PaymentOperator::OrangeMoney, 2],
            [PaymentOperator::MtnMobileMoney, 3],
        ] as [$operateur, $combien]) {
            for ($i = 0; $i < $combien; $i++) {
                $demande = $this->demande->replicate();
                $demande->reference = ReissuanceRequest::generateReference();
                $demande->save();

                $service->reconcile($service->initiate($demande, $this->citoyen, '+23760000000'.$i, $operateur));
            }
        }

        $comptes = Payment::query()
            ->where('status', 'settled')
            ->selectRaw('operator, count(*) as total')
            ->groupBy('operator')
            ->pluck('total', 'operator')
            ->all();

        $this->assertSame(2, (int) $comptes['orange_money']);
        $this->assertSame(3, (int) $comptes['mtn_mobile_money']);
    }
}
