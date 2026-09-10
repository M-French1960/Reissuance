<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentReceipt;
use App\Services\PaymentService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le contenu du recu, relu par un outil TIERS.
 *
 * Comme pour l'acte au jalon 5 : on n'affirme pas que le PDF dit quelque
 * chose, on le fait relire par pdftotext. Un PDF que seul le code qui l'a
 * ecrit sait relire ne prouve rien.
 */
class PaymentReceiptTest extends TestCase
{
    private ReissuanceRequest $demande;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'phoenix.payments.amount_minor' => '1500',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
            'phoenix.payments.legal_basis' => '',
        ]);

        $centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-330000001', 'completed_at' => now(),
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

    private function recu(): string
    {
        $service = app(PaymentService::class);
        $paiement = $service->reconcile(
            $service->initiate($this->demande, $this->citoyen, '+237600000001')
        );

        return app(PaymentReceipt::class)->build($paiement->load('request.citizen.profile'));
    }

    private function texte(string $pdf): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'recu').'.pdf';
        file_put_contents($chemin, $pdf);

        try {
            exec('pdftotext -layout '.escapeshellarg($chemin).' - 2>/dev/null', $lignes, $code);
            $this->assertSame(0, $code, 'pdftotext ne relit pas le reçu : le PDF est invalide.');

            return implode("\n", $lignes);
        } finally {
            @unlink($chemin);
        }
    }

    #[Test]
    public function le_recu_est_un_pdf_valide_et_relisible(): void
    {
        $pdf = $this->recu();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('RECU DE REGLEMENT', $this->texte($pdf));
    }

    /**
     * La mention de demonstration est la PREMIERE ligne non vide.
     *
     * Meme regle qu'a l'acte (D-025) : un recu de demonstration ne doit
     * pouvoir etre confondu avec une quittance par personne, y compris par un
     * agent de bonne foi qui ne lit que le haut de la page.
     */
    #[Test]
    public function la_mention_de_demonstration_figure_des_la_premiere_ligne(): void
    {
        $texte = $this->texte($this->recu());

        $premiere = '';
        foreach (explode("\n", $texte) as $ligne) {
            if (trim($ligne) !== '') {
                $premiere = trim($ligne);
                break;
            }
        }

        $this->assertStringContainsString(PaymentReceipt::DEMO_NOTICE, $premiere);
    }

    #[Test]
    public function le_recu_porte_le_montant_effectivement_encaisse(): void
    {
        $texte = $this->texte($this->recu());

        $this->assertStringContainsString('1 500 XAF', $texte);
        $this->assertStringContainsString($this->demande->reference, $texte);
    }

    /** La date se lit, sans marqueur am/pm parasite. */
    #[Test]
    public function la_date_du_reglement_est_lisible(): void
    {
        $texte = $this->texte($this->recu());

        $this->assertMatchesRegularExpression('#Date du reglement\s*:\s*\d{2}/\d{2}/\d{4} \d{2}:\d{2}#', $texte);
        $this->assertStringNotContainsString(' pm ', $texte);
        $this->assertStringNotContainsString(' am ', $texte);
    }

    /**
     * Sans base reglementaire configuree, le recu n'en invente pas.
     *
     * Le 10 du brief : on ne cite pas un texte qu'on ne peut pas verifier.
     */
    #[Test]
    public function sans_base_reglementaire_le_recu_n_en_imprime_aucune(): void
    {
        $texte = $this->texte($this->recu());

        $this->assertStringNotContainsString('Base reglementaire', $texte);
        $this->assertStringNotContainsString('Base réglementaire', $texte);
    }

    #[Test]
    public function une_base_reglementaire_configuree_figure_au_recu(): void
    {
        config(['phoenix.payments.legal_basis' => 'Arrêté n° X du 1er janvier 2000']);

        $texte = $this->texte($this->recu());

        $this->assertStringContainsString('Base reglementaire', $texte);
        $this->assertStringContainsString('du 1er janvier 2000', $texte);
    }
}
