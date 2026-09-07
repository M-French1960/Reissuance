<?php

declare(strict_types=1);

namespace Tests\Feature\EndToEnd;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le parcours complet, du brouillon a l'acte signe, PAR LES ROUTES SEULEMENT.
 *
 * Tous les autres tests de fonctionnalites appellent les services pour
 * amener une demande dans l'etat qui les interesse. C'est legitime : ils
 * testent une regle, pas un chemin. Mais aucun d'eux ne prouve que le chemin
 * existe, et l'absence de ce test a laisse passer un blocage complet du
 * parcours officier (D-027) : la demande n'a jamais pu quitter under_review
 * par l'interface depuis le jalon 4.
 *
 * Ce test n'appelle donc AUCUN service applicatif. Il clique. Si un ecran
 * manque, si une route refuse, si une condition est inatteignable, il casse.
 */
class CompleteJourneyTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private User $maire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create(['is_active' => true]);
        $this->citoyen = User::factory()->citizen()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
    }

    #[Test]
    public function du_brouillon_a_l_acte_signe_sans_appeler_un_seul_service(): void
    {
        $reference = $this->parcoursCitoyen();

        $demande = ReissuanceRequest::withoutGlobalScopes()
            ->where('reference', $reference)->firstOrFail();

        $this->assertSame(RequestStatus::Pending, $demande->status);

        $this->parcoursOfficier($demande);

        $demande->refresh();
        $this->assertSame(
            RequestStatus::AwaitingSignature,
            $demande->status,
            "L'officier n'a pas pu transmettre la demande au maire par l'interface."
        );

        $this->parcoursMaire($demande);

        $demande->refresh();
        $this->assertSame(RequestStatus::Signed, $demande->status);

        $this->telechargementParLeCitoyen($demande);
        $this->traceComplete($demande);
    }

    /** Profil, assistant en quatre etapes, deux pieces, envoi. */
    private function parcoursCitoyen(): string
    {
        $this->actingAs($this->citoyen);

        $this->patch(route('citizen.profile.update'), [
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'birth_date' => '1990-01-15',
            'birth_place' => 'Yaoundé',
            'national_id_number' => 'DEMO-900000001',
            'phone' => '+237 6 00 00 00 01',
            'address' => 'Adresse de test',
        ])->assertSessionHasNoErrors();

        // Chaque requete HTTP reelle repart d'une instance fraiche de
        // l'utilisateur ; actingAs, lui, garde la meme pour tout le test.
        // On la rafraichit pour ne pas tester un cache de relation.
        $this->actingAs($this->citoyen->fresh());

        $this->post(route('citizen.requests.start'))->assertRedirect();

        $brouillon = ReissuanceRequest::withoutGlobalScopes()
            ->where('user_id', $this->citoyen->id)->latest('id')->firstOrFail();

        // Chaque etape est d'abord AFFICHEE : c'est ce qui a manque au jalon 3,
        // ou l'etape 4 renvoyait 500 sans qu'aucun test ne la rende (D-026).
        $this->get(route('citizen.requests.step', [$brouillon, 1]))->assertOk();
        $this->post(route('citizen.requests.save', [$brouillon, 1]), [
            'reason' => 'lost',
            'copies_requested' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('citizen.requests.step', [$brouillon, 2]));

        $this->get(route('citizen.requests.step', [$brouillon, 2]))->assertOk();
        $this->post(route('citizen.requests.save', [$brouillon, 2]), [
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST',
            'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST',
            'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ])->assertSessionHasNoErrors()->assertRedirect(route('citizen.requests.step', [$brouillon, 3]));

        $this->get(route('citizen.requests.step', [$brouillon, 3]))->assertOk();
        $this->post(route('citizen.requests.save', [$brouillon, 3]), [
            'civil_status_center_id' => $this->centre->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('citizen.requests.step', [$brouillon, 4]));

        // Envoi sans les pieces : refuse, et la demande reste un brouillon.
        $this->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertSessionHasErrors('attachments');
        $this->assertSame(RequestStatus::Draft, $brouillon->refresh()->status);

        foreach (['selfie', 'id_document'] as $type) {
            $this->post(route('citizen.requests.attachments.store', $brouillon), [
                'kind' => $type,
                'file' => UploadedFile::fake()->image("{$type}.jpg", 800, 600),
            ])->assertSessionHasNoErrors();
        }

        $this->get(route('citizen.requests.step', [$brouillon, 4]))->assertOk();
        $this->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.requests.show', $brouillon));

        return $brouillon->refresh()->reference;
    }

    /** File, prise en charge, les cinq ecrans, decision d'acceptation. */
    private function parcoursOfficier(ReissuanceRequest $demande): void
    {
        $this->actingAs($this->officier);

        $this->get(route('officer.queue'))->assertOk()->assertSee($demande->reference);

        $this->post(route('officer.verification.claim', $demande))
            ->assertRedirect(route('officer.verification.step', [$demande, 1]));

        // Etape 1 : constat de l'officier.
        $this->get(route('officer.verification.step', [$demande, 1]))->assertOk();
        $this->post(route('officer.verification.acknowledge', [$demande, 1]), [
            'result' => 'match',
        ])->assertSessionHasNoErrors();

        // Etape 2 : appel a la base de la police.
        $this->get(route('officer.verification.step', [$demande, 2]))->assertOk();
        $this->post(route('officer.verification.identity', $demande))
            ->assertSessionHasNoErrors();

        // Etape 3 : examen des photographies.
        $this->get(route('officer.verification.step', [$demande, 3]))->assertOk();
        $this->post(route('officer.verification.acknowledge', [$demande, 3]), [
            'result' => 'match',
        ])->assertSessionHasNoErrors();

        // Etape 4 : recherche au registre.
        $this->get(route('officer.verification.step', [$demande, 4]))->assertOk();
        $this->post(route('officer.verification.registry', $demande))
            ->assertSessionHasNoErrors();

        // Etape 5 : la decision. Rien d'autre ne reste a renseigner.
        $this->get(route('officer.verification.step', [$demande, 5]))
            ->assertOk()
            ->assertDontSee('Vérification incomplète');

        $this->post(route('officer.decision.store', $demande), [
            'decision' => 'accepted',
        ])->assertSessionHasNoErrors()->assertRedirect(route('officer.queue'));
    }

    private function parcoursMaire(ReissuanceRequest $demande): void
    {
        $this->actingAs($this->maire);

        $this->get(route('mayor.dashboard'))->assertOk()->assertSee($demande->reference);
        $this->get(route('mayor.review', $demande))->assertOk();

        $this->post(route('mayor.sign', $demande))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mayor.dashboard'));
    }

    private function telechargementParLeCitoyen(ReissuanceRequest $demande): void
    {
        $signature = $demande->signature;
        $this->assertNotNull($signature);

        $this->actingAs($this->citoyen)
            ->get(route('acts.document', $signature))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * La trace : quatre transitions journalisees, dans l'ordre, plus la
     * delivrance de l'acte. C'est ce qu'un audit anti-fraude doit pouvoir
     * reconstituer sans consulter autre chose que le journal.
     */
    private function traceComplete(ReissuanceRequest $demande): void
    {
        $transitions = AuditLog::query()
            ->where('auditable_type', 'reissuance_request')
            ->where('auditable_id', $demande->id)
            ->where('action', 'like', 'request.%_to_%')
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame([
            'request.draft_to_pending',
            'request.pending_to_under_review',
            'request.under_review_to_awaiting_signature',
            'request.awaiting_signature_to_signed',
        ], $transitions);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $demande->signature->id,
            'action' => 'act.issued',
        ]);
    }
}
