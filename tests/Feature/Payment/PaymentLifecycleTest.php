<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Contracts\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Integrations\Fake\FakePaymentProvider;
use App\Integrations\Real\HrSkillsPayPaymentProvider;
use App\Models\CivilStatusCenter;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PaymentLifecycleTest extends TestCase
{
    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        // Un tarif d'ESSAI, pose par le test. Le depot n'en contient aucun.
        config([
            'phoenix.payments.amount_minor' => '1000',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
        ]);

        $centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-310000001', 'completed_at' => now(),
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

    private function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    #[Test]
    public function un_ordre_pris_en_compte_ne_vaut_pas_paiement(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');

        $this->assertSame(PaymentStatus::Authorised, $paiement->status);
        $this->assertFalse($paiement->isPaid(), '« Autorisé » ne doit pas valoir « payé ».');
        $this->assertFalse($this->service()->isPaid($this->demande));
        $this->assertNotNull($paiement->authorised_at);
        $this->assertNull($paiement->settled_at);
    }

    #[Test]
    public function seul_l_acquittement_des_fonds_vaut_paiement(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');
        $paiement = $this->service()->reconcile($paiement);

        $this->assertSame(PaymentStatus::Settled, $paiement->status);
        $this->assertTrue($this->service()->isPaid($this->demande));
        $this->assertNotNull($paiement->settled_at);
    }

    /**
     * LE test de l'encaissement mobile : un rappel rejoue ne facture pas deux
     * fois. Les operateurs rejouent, c'est leur fonctionnement normal.
     */
    #[Test]
    public function un_rappel_rejoue_ne_cree_ni_seconde_transition_ni_seconde_ligne_d_audit(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');
        $paiement = $this->service()->reconcile($paiement);

        $lignesAvant = DB::table('audit_logs')
            ->where('auditable_type', 'payment')->where('auditable_id', $paiement->id)->count();

        // Le meme evenement, trois fois de plus.
        foreach ([1, 2, 3] as $ignore) {
            $paiement = $this->service()->apply($paiement, new PaymentOutcome(
                PaymentStatus::Settled,
                FakePaymentProvider::PROVIDER,
                $paiement->provider_reference,
                'Fonds acquis.',
            ));
        }

        $this->assertSame(PaymentStatus::Settled, $paiement->status);
        $this->assertSame(1, Payment::where('request_id', $this->demande->id)->count());
        $this->assertSame($lignesAvant, DB::table('audit_logs')
            ->where('auditable_type', 'payment')->where('auditable_id', $paiement->id)->count());
    }

    #[Test]
    public function une_demande_n_a_qu_un_encaissement_vivant_a_la_fois(): void
    {
        $premier = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');
        $second = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, Payment::where('request_id', $this->demande->id)->count());
    }

    #[Test]
    public function un_refus_porte_un_motif(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, 'DEMO-REFUS-001');

        $this->assertSame(PaymentStatus::Failed, $paiement->status);
        $this->assertNotNull($paiement->failure_reason);
        $this->assertFalse($this->service()->isPaid($this->demande));
    }

    /** Les declencheurs du fournisseur factice resistent a la ponctuation (D-021). */
    #[Test]
    public function les_declencheurs_resistent_a_la_ponctuation(): void
    {
        foreach (['DEMO-REFUS-9', 'demo refus 9', 'DeMo.ReFuS.9'] as $ecriture) {
            $demande = $this->demande->replicate();
            $demande->reference = ReissuanceRequest::generateReference();
            $demande->save();

            $paiement = $this->service()->initiate($demande, $this->citoyen, $ecriture);

            $this->assertSame(
                PaymentStatus::Failed,
                $paiement->status,
                "L'écriture « {$ecriture} » n'a pas déclenché le refus."
            );
        }
    }

    #[Test]
    public function une_panne_de_l_operateur_n_est_ni_un_paiement_ni_un_refus(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, 'DEMO-PANNE-1');

        $this->assertSame(PaymentStatus::Expired, $paiement->status);
        $this->assertFalse($this->service()->isPaid($this->demande));
    }

    #[Test]
    public function un_etat_terminal_ne_se_quitte_plus(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, 'DEMO-REFUS-002');

        $this->expectException(DomainException::class);

        $this->service()->apply($paiement, new PaymentOutcome(
            PaymentStatus::Settled, FakePaymentProvider::PROVIDER, 'X', 'Tentative.'
        ));
    }

    /** La base refuse elle aussi, meme si le service etait contourne. */
    #[Test]
    public function la_base_refuse_une_transition_interdite_sans_passer_par_le_service(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/interdite|refusee/');

        DB::table('payments')->where('id', $paiement->id)->update(['status' => 'refunded']);
    }

    /** Et elle refuse une transition autorisee mais non journalisee. */
    #[Test]
    public function la_base_refuse_une_transition_sans_ligne_d_audit(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches("/aucune ligne d'audit/");

        DB::table('payments')->where('id', $paiement->id)
            ->update(['status' => 'settled', 'settled_at' => now()]);
    }

    #[Test]
    public function un_remboursement_exige_un_encaissement_acquis_et_un_motif(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');
        $agent = User::factory()->admin()->create();

        // Pas encore acquis.
        try {
            $this->service()->refund($paiement, $agent, 'Motif suffisamment explicite.');
            $this->fail('Un remboursement a été accepté sur un paiement non acquis.');
        } catch (DomainException) {
            // Attendu.
        }

        $paiement = $this->service()->reconcile($paiement);

        // Acquis, mais sans motif utilisable.
        try {
            $this->service()->refund($paiement, $agent, 'court');
            $this->fail('Un remboursement sans motif explicite a été accepté.');
        } catch (DomainException) {
            // Attendu.
        }

        $paiement = $this->service()->refund($paiement, $agent, 'Demande rejetée : frais rendus au demandeur.');

        $this->assertSame(PaymentStatus::Refunded, $paiement->status);
        $this->assertNotNull($paiement->refunded_at);
        $this->assertFalse($this->service()->isPaid($this->demande));
    }

    /**
     * Sans tarif configure, la plateforme REFUSE de servir.
     *
     * C'est le garde-fou du 10 du brief : on ne code pas une hypothese
     * reglementaire, et on ne facture pas un chiffre invente (D-039).
     */
    #[Test]
    public function sans_tarif_configure_aucun_encaissement_n_est_ouvert(): void
    {
        config(['phoenix.payments.amount_minor' => null]);

        try {
            $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');
            $this->fail('Un encaissement a été ouvert sans tarif configuré.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Aucun tarif', $e->getMessage());
        }

        $this->assertSame(0, Payment::where('request_id', $this->demande->id)->count());
    }

    #[Test]
    public function le_montant_encaisse_est_conserve_meme_si_le_tarif_change(): void
    {
        $paiement = $this->service()->initiate($this->demande, $this->citoyen, '+237600000001');

        config(['phoenix.payments.amount_minor' => '9999']);

        $this->assertSame(1000, $paiement->refresh()->amount_minor);
        $this->assertSame('1 000 XAF', $paiement->money()->format());
    }

    /**
     * L'adaptateur reel refuse de servir sans identifiants.
     *
     * Il n'est plus un squelette : HR-Skills Pay est implemente (D-050). Ce
     * qu'on verifie ici, c'est qu'il echoue BRUYAMMENT quand il n'est pas
     * configure, plutot que de tenter un appel voue a l'echec ou, pire, de
     * rendre un succes.
     */
    #[Test]
    public function l_adaptateur_reel_refuse_de_servir_sans_identifiants(): void
    {
        config([
            'phoenix.payments.hrskills.public_key' => '',
            'phoenix.payments.hrskills.secret_key' => '',
        ]);

        $this->app->bind(PaymentProvider::class, fn () => new HrSkillsPayPaymentProvider);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/PHOENIX_HRSKILLS_/');

        app(PaymentService::class)->initiate($this->demande, $this->citoyen, '+237600000001');
    }

    /**
     * Le service et le declencheur doivent decrire la MEME machine.
     *
     * Deux tables de transitions qui divergent, c'est un refus cote base que
     * le code croyait autorise — ou l'inverse, ce qui est pire.
     *
     * La version PostgreSQL de ce test lisait pg_proc.prosrc et se contentait
     * de chercher l'etat de DEPART dans la source. Elle serait passee au vert
     * sur un declencheur autorisant n'importe quelle cible depuis cet etat :
     * elle ne verifiait jamais la PAIRE. Ici on interroge la base sur chacune
     * des paires possibles, dans les deux sens : ce que le service autorise,
     * la base doit l'accepter ; ce qu'il refuse, elle doit le refuser.
     */
    #[Test]
    public function le_service_et_le_declencheur_decrivent_la_meme_machine(): void
    {
        $etats = array_map(
            static fn (PaymentStatus $statut): string => $statut->value,
            PaymentStatus::cases(),
        );

        $divergences = [];

        foreach ($etats as $depuis) {
            foreach ($etats as $vers) {
                if ($depuis === $vers) {
                    continue;
                }

                $serviceAutorise = in_array($vers, PaymentService::TRANSITIONS[$depuis] ?? [], true);
                $baseAutorise = $this->laBaseAccepteLaTransition($depuis, $vers);

                if ($serviceAutorise !== $baseAutorise) {
                    $divergences[] = sprintf(
                        '%s → %s : service=%s, base=%s',
                        $depuis,
                        $vers,
                        $serviceAutorise ? 'autorisé' : 'refusé',
                        $baseAutorise ? 'autorisé' : 'refusé',
                    );
                }
            }
        }

        $this->assertSame([], $divergences, "Le service et le déclencheur divergent :\n".implode("\n", $divergences));
    }

    /**
     * La base accepte-t-elle cette transition ?
     *
     * On la tente reellement, sur une ligne jetable, avec la ligne d'audit que
     * le declencheur exige — sans quoi TOUTE transition serait refusee et le
     * test serait vert pour la mauvaise raison. Le point de sauvegarde annule
     * l'ecriture quelle que soit l'issue.
     */
    private function laBaseAccepteLaTransition(string $depuis, string $vers): bool
    {
        DB::beginTransaction();

        try {
            // Ecriture directe : le modele et le service appliquent leurs
            // propres regles, et c'est la base seule qu'on interroge ici.
            $identifiant = DB::table('payments')->insertGetId([
                'request_id' => $this->demande->id,
                'initiated_by' => $this->citoyen->id,
                'amount_minor' => 1000,
                'currency' => 'XAF',
                'minor_unit' => 0,
                'status' => $depuis,
                'provider' => 'fake-mobile-money',
                'idempotency_key' => 'essai-'.bin2hex(random_bytes(8)),
                'created_at' => now(),
                'updated_at' => now(),
                ...self::colonnesLieesAuStatut($depuis),
            ]);

            DB::table('audit_logs')->insert([
                'action' => 'payment.transition.probe',
                'auditable_type' => 'payment',
                'auditable_id' => $identifiant,
                'from_status' => $depuis,
                'to_status' => $vers,
                'created_at' => now(),
            ]);

            DB::table('payments')->where('id', $identifiant)->update([
                'status' => $vers,
                ...self::colonnesLieesAuStatut($vers),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Les colonnes que les contraintes CHECK exigent pour un statut donne.
     *
     * Sans elles, la sonde se ferait refuser par une contrainte CHECK et non
     * par le declencheur : le test conclurait « la base refuse cette
     * transition » alors qu'elle refusait une ligne incoherente. On isole donc
     * la question posee — le declencheur — en satisfaisant tout le reste.
     *
     * @return array<string, string|null>
     */
    private static function colonnesLieesAuStatut(string $statut): array
    {
        $horodatage = now()->toDateTimeString();

        return [
            // `settled_at is null or status in ('settled','refunded')`
            'settled_at' => in_array($statut, ['settled', 'refunded'], true) ? $horodatage : null,
            // `refunded_at is null or status = 'refunded'`
            'refunded_at' => $statut === 'refunded' ? $horodatage : null,
            // `status <> 'failed' or failure_reason is not null`
            'failure_reason' => $statut === 'failed' ? 'sonde de test' : null,
        ];
    }

    /**
     * Le prestataire echoue a la premiere prise de contact : le citoyen doit
     * pouvoir reessayer.
     *
     * LE DEFAUT : la ligne de paiement est validee en base AVANT l'appel a
     * l'operateur — il le faut, la cle d'idempotence doit exister avant d'etre
     * envoyee. Si l'appel echouait ensuite, la ligne restait « en attente »
     * sans reference prestataire, `initiate()` la rendait telle quelle sans
     * jamais rappeler l'operateur, et `reconcile()` s'arretait faute de
     * reference. Le citoyen ne pouvait plus JAMAIS payer sa demande.
     */
    #[Test]
    public function un_paiement_dont_la_premiere_prise_de_contact_a_echoue_peut_etre_repris(): void
    {
        $prestataire = new class implements PaymentProvider
        {
            public int $appels = 0;

            /** @var list<string> */
            public array $clesVues = [];

            public function initiate(PaymentIntent $intent): PaymentOutcome
            {
                $this->appels++;
                $this->clesVues[] = $intent->idempotencyKey;

                if ($this->appels === 1) {
                    throw new RuntimeException('numéro de téléphone invalide');
                }

                return new PaymentOutcome(PaymentStatus::Pending, 'prestataire-essai', 'REF-'.$this->appels);
            }

            public function status(string $providerReference): PaymentOutcome
            {
                return new PaymentOutcome(PaymentStatus::Settled, 'prestataire-essai', $providerReference);
            }

            public function refund(Payment $payment, Money $amount, string $reason): PaymentOutcome
            {
                throw new RuntimeException('hors sujet ici');
            }
        };

        $this->app->instance(PaymentProvider::class, $prestataire);
        $service = app(PaymentService::class);

        try {
            $service->initiate($this->demande, $this->citoyen, '+237600000000');
            $this->fail('La premiere prise de contact aurait du echouer.');
        } catch (RuntimeException) {
            // Attendu : l'echec remonte, la ligne reste en base.
        }

        $bloque = $service->livePayment($this->demande);
        $this->assertNotNull($bloque);
        $this->assertNull($bloque->provider_reference, "Aucun ordre n'existe chez l'opérateur.");

        // Le demandeur corrige son numero et recommence.
        $repris = $service->initiate($this->demande, $this->citoyen, '+237699999999');

        $this->assertSame('REF-2', $repris->provider_reference, "L'opérateur doit avoir été rappelé.");
        $this->assertSame('+237699999999', $repris->payer_reference, 'La correction doit être prise en compte.');

        // LA propriete de surete : un seul ordre, une seule cle.
        $this->assertSame(1, Payment::where('request_id', $this->demande->id)->count());
        $this->assertCount(
            1,
            array_unique($prestataire->clesVues),
            "La cle d'idempotence doit etre reutilisee : en changer ouvrirait un second ordre chez l'operateur, donc un risque de double prelevement."
        );

        // Et le rapprochement redevient possible.
        $this->assertSame(PaymentStatus::Settled, $service->reconcile($repris->refresh())->status);
    }

    /** Un ordre reellement ouvert chez l'operateur n'est jamais rejoue. */
    #[Test]
    public function un_ordre_deja_ouvert_chez_l_operateur_n_est_pas_relance(): void
    {
        $service = app(PaymentService::class);

        $premier = $service->initiate($this->demande, $this->citoyen, '+237600000000');
        $this->assertNotNull($premier->provider_reference);

        $second = $service->initiate($this->demande, $this->citoyen, '+237611111111');

        $this->assertSame($premier->id, $second->id);
        $this->assertSame($premier->provider_reference, $second->provider_reference);
        $this->assertSame(1, Payment::where('request_id', $this->demande->id)->count());
    }
}
