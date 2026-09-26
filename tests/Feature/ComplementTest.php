<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestAttachment;
use App\Models\RequestComplement;
use App\Models\User;
use App\Notifications\ComplementProvided;
use App\Notifications\ComplementRequested;
use App\Services\IdentityDocumentStore;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reclamer une piece apres l'envoi, et y repondre (D-087).
 *
 * LA LIMITE QUE CELA LEVE : une fois la demande envoyee, le demandeur ne
 * pouvait plus toucher a ses photos. Une photo floue condamnait le dossier au
 * rejet d'une personne de bonne foi.
 *
 * CE QUE CES TESTS SURVEILLENT, par ordre d'importance :
 *
 * 1. Qu'on ne puisse pas remplacer une piece SANS qu'un officier l'ait
 *    reclamee. Sans cette borne, n'importe qui remplacerait apres coup la
 *    piece deja verifiee.
 * 2. Que le remplacement d'une piece d'identite RELANCE la verification. Sans
 *    cela, un acte pourrait etre signe sur la foi de controles portant sur un
 *    document qui a ete remplace depuis — §4.3 du brief.
 * 3. Que la porte se referme une fois la piece fournie.
 */
class ComplementTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-700000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
        $this->demande->refresh();
    }

    #[Test]
    public function l_officier_reclame_une_piece_et_le_demandeur_est_averti(): void
    {
        Notification::fake();

        $this->actingAs($this->officier)
            ->post(route('officer.complement.store', $this->demande), [
                'kind' => 'id_document',
                'message' => 'Le numéro est masqué par un reflet, reprenez la photo sans flash.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('request_complements', [
            'request_id' => $this->demande->id,
            'requested_by' => $this->officier->id,
            'kind' => 'id_document',
            'fulfilled_at' => null,
        ]);

        Notification::assertSentTo($this->citoyen, ComplementRequested::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request.complement_requested',
            'actor_id' => $this->officier->id,
        ]);
    }

    /**
     * Un motif trop court est refuse.
     *
     * « Photo floue » ne dit pas quoi refaire : le demandeur renverrait la meme
     * photo, et la boucle recommencerait a ses frais.
     */
    #[Test]
    public function un_motif_trop_court_est_refuse(): void
    {
        $this->actingAs($this->officier)
            ->post(route('officer.complement.store', $this->demande), [
                'kind' => 'id_document',
                'message' => 'Flou.',
            ])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('request_complements', 0);
    }

    /**
     * LE TEST LE PLUS IMPORTANT DE CE FICHIER.
     *
     * Sans complement ouvert, la porte n'existe pas. Si ce test tombe,
     * n'importe qui peut remplacer apres coup la piece d'identite deja
     * verifiee par une autre.
     */
    #[Test]
    public function sans_piece_reclamee_le_demandeur_ne_peut_rien_remplacer(): void
    {
        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.complement', $this->demande))
            ->assertForbidden();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.complement.store', $this->demande), [
                'file' => UploadedFile::fake()->image('piece.jpg'),
            ])
            ->assertForbidden();
    }

    #[Test]
    public function le_demandeur_repond_et_la_piece_est_remplacee(): void
    {
        Notification::fake();
        $this->reclamer();

        $ancienne = $this->deposerUnePiece();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.complement.store', $this->demande), [
                'file' => UploadedFile::fake()->image('nouvelle.jpg', 900, 600),
            ])
            ->assertRedirect(route('citizen.requests.show', $this->demande));

        // L'ancienne piece est remplacee, et son fichier supprime : une piece
        // d'identite refusee n'est pas conservee.
        $this->assertDatabaseMissing('request_attachments', ['id' => $ancienne->id]);
        Storage::disk('private')->assertMissing($ancienne->path);

        $this->assertSame(1, $this->demande->attachments()->where('kind', 'id_document')->count());

        $complement = RequestComplement::firstOrFail();
        $this->assertNotNull($complement->fulfilled_at);
        $this->assertNotNull($complement->fulfilled_attachment_id);

        Notification::assertSentTo($this->officier, ComplementProvided::class);
    }

    /**
     * LE SECOND TEST LE PLUS IMPORTANT.
     *
     * Remplacer une piece d'identite ouvre un nouveau cycle de verification.
     * Sans cela, les etapes franchies sur l'ANCIENNE photo resteraient valides
     * pour la nouvelle, et un acte pourrait etre signe sur la foi d'un controle
     * portant sur un document remplace depuis.
     */
    #[Test]
    public function remplacer_une_piece_d_identite_relance_la_verification(): void
    {
        Notification::fake();

        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }
        $this->demande->refresh();

        $cycleAvant = $this->demande->verification_cycle;

        $this->reclamer();
        $this->deposerUnePiece();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.complement.store', $this->demande), [
                'file' => UploadedFile::fake()->image('nouvelle.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->demande->refresh();

        $this->assertSame(
            $cycleAvant + 1,
            $this->demande->verification_cycle,
            'Le remplacement d’une pièce d’identité doit ouvrir un nouveau cycle.'
        );

        // Les etapes du cycle precedent sont CONSERVEES — elles disent ce qui a
        // ete verifie, et sur quoi — mais elles ne comptent plus.
        $this->assertSame(3, $this->demande->verificationSteps()->where('cycle', $cycleAvant)->count());
        $this->assertSame(0, $this->demande->verificationSteps()->where('cycle', $cycleAvant + 1)->count());
    }

    /** La porte se referme une fois la piece fournie. */
    #[Test]
    public function la_porte_se_referme_une_fois_la_piece_fournie(): void
    {
        Notification::fake();
        $this->reclamer();
        $this->deposerUnePiece();

        $this->actingAs($this->citoyen)->post(route('citizen.requests.complement.store', $this->demande), [
            'file' => UploadedFile::fake()->image('nouvelle.jpg'),
        ]);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.complement', $this->demande))
            ->assertForbidden();
    }

    /** Deux demandes ouvertes a la fois n'ont pas de sens : la base le refuse. */
    #[Test]
    public function une_seule_piece_peut_etre_reclamee_a_la_fois(): void
    {
        Notification::fake();
        $this->reclamer();

        $this->actingAs($this->officier)
            ->post(route('officer.complement.store', $this->demande), [
                'kind' => 'selfie',
                'message' => 'Le visage est trop sombre, reprenez la photo en plein jour.',
            ])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('request_complements', 1);
    }

    /** Un officier qui ne tient pas le dossier ne reclame rien. */
    #[Test]
    public function un_officier_non_affecte_ne_reclame_rien(): void
    {
        $collegue = User::factory()->officer($this->centre)->create();

        $this->actingAs($collegue)
            ->post(route('officer.complement.store', $this->demande), [
                'kind' => 'id_document',
                'message' => 'Le numéro est masqué par un reflet, reprenez la photo.',
            ])
            ->assertForbidden();
    }

    /** Un autre demandeur n'atteint pas le dossier de quelqu'un d'autre. */
    #[Test]
    public function un_autre_demandeur_n_atteint_pas_ce_dossier(): void
    {
        Notification::fake();
        $this->reclamer();

        $this->actingAs(User::factory()->citizen()->create())
            ->get(route('citizen.requests.complement', $this->demande))
            ->assertNotFound();
    }

    /** L'ecran dit CE QUI ne va pas, avec les mots de l'officier. */
    #[Test]
    public function l_ecran_cite_le_motif_de_l_officier(): void
    {
        Notification::fake();
        $this->reclamer();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.complement', $this->demande))
            ->assertOk()
            ->assertSee('Le numéro est masqué par un reflet, reprenez la photo sans flash.')
            ->assertSee(__('citizen.complement.restarts_body'));
    }

    /** Le bandeau de suivi annonce la piece attendue. */
    #[Test]
    public function le_suivi_annonce_la_piece_attendue(): void
    {
        Notification::fake();
        $this->reclamer();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $this->demande))
            ->assertOk()
            ->assertSee(__('citizen.complement.banner_title'))
            ->assertSee(__('citizen.complement.banner_action'));
    }

    private function reclamer(string $kind = 'id_document'): void
    {
        $this->actingAs($this->officier)
            ->post(route('officer.complement.store', $this->demande), [
                'kind' => $kind,
                'message' => 'Le numéro est masqué par un reflet, reprenez la photo sans flash.',
            ])
            ->assertSessionHasNoErrors();
    }

    /** Une piece deja au dossier, pour verifier qu'elle est bien remplacee. */
    private function deposerUnePiece(): RequestAttachment
    {
        return app(IdentityDocumentStore::class)->store(
            $this->demande,
            UploadedFile::fake()->image('ancienne.jpg'),
            'id_document',
            $this->citoyen,
        );
    }
}
