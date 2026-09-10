<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestMessage;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * « Contact Officer » du diagramme de cas d'utilisation.
 *
 * Un fil attache a une DEMANDE, pas une messagerie entre personnes : on ecrit
 * au sujet d'un dossier. N'importe quel officier du centre peut donc reprendre
 * la conversation, et chaque echange est rattache a une trace.
 */
class RequestMessageTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-350000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        $this->demande->refresh();
    }

    #[Test]
    public function le_demandeur_ecrit_au_centre(): void
    {
        $this->actingAs($this->citoyen)
            ->post(route('requests.messages.store', $this->demande), [
                'body' => "J'ai déménagé, mon numéro de téléphone a changé.",
            ])->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.requests.show', $this->demande));

        $this->assertDatabaseHas('request_messages', [
            'request_id' => $this->demande->id,
            'author_id' => $this->citoyen->id,
            'author_role' => 'citizen',
        ]);
    }

    #[Test]
    public function l_officier_du_centre_repond(): void
    {
        $this->actingAs($this->officier)
            ->post(route('requests.messages.store', $this->demande), [
                'body' => 'Bien noté, votre dossier suit son cours.',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('request_messages', [
            'request_id' => $this->demande->id,
            'author_id' => $this->officier->id,
            'author_role' => 'officer',
        ]);
    }

    /** Le fil suit le DOSSIER : un autre officier du centre le reprend. */
    #[Test]
    public function un_autre_officier_du_meme_centre_peut_reprendre_le_fil(): void
    {
        $remplacant = User::factory()->officer($this->centre)->create();

        $this->actingAs($remplacant)
            ->post(route('requests.messages.store', $this->demande), [
                'body' => "Je reprends ce dossier en l'absence de mon collègue.",
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('request_messages', ['author_id' => $remplacant->id]);
    }

    /**
     * Le maire ecrit — mais seulement quand le dossier lui est parvenu.
     *
     * Sa portee globale ne lui montre que `awaiting_signature` et `escalated`.
     * Sur une demande `pending`, la liaison de modele echoue AVANT la Policy :
     * il obtient 404, ce qui ne confirme meme pas l'existence du dossier. La
     * Policy reste ecrite en termes de commune, comme deuxieme barriere.
     */
    #[Test]
    public function le_maire_ecrit_une_fois_le_dossier_parvenu_jusqu_a_lui(): void
    {
        $maire = User::factory()->mayor($this->centre->commune)->create();

        // Tant que le dossier est en attente de traitement, il ne le voit pas.
        $this->actingAs($maire)
            ->post(route('requests.messages.store', $this->demande), ['body' => 'Bonjour.'])
            ->assertNotFound();

        $this->porterJusquALaSignature();

        $this->actingAs($maire)
            ->post(route('requests.messages.store', $this->demande->refresh()), [
                'body' => 'Merci de préciser le lieu de naissance exact.',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('request_messages', ['author_role' => 'mayor']);
    }

    private function porterJusquALaSignature(): void
    {
        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($this->demande->refresh(), $n, $this->officier, VerificationResult::Match);
        }

        $transitions->transition($this->demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);
        $this->demande->refresh();
    }

    /* --------------------------------------------------------- les refus */

    #[Test]
    public function un_autre_citoyen_n_ecrit_pas_sur_mon_dossier(): void
    {
        $autre = User::factory()->citizen()->create();

        $this->actingAs($autre)
            ->post(route('requests.messages.store', $this->demande), ['body' => 'Bonjour.'])
            ->assertNotFound();

        $this->assertSame(0, RequestMessage::count());
    }

    #[Test]
    public function un_officier_d_un_autre_centre_n_ecrit_pas(): void
    {
        $etranger = User::factory()->officer(CivilStatusCenter::factory()->create())->create();

        $this->actingAs($etranger)
            ->post(route('requests.messages.store', $this->demande), ['body' => 'Bonjour.'])
            ->assertNotFound();

        $this->assertSame(0, RequestMessage::count());
    }

    /**
     * L'administrateur est exclu, et ce n'est pas un oubli.
     *
     * Le 4.2 lui interdit le contenu des dossiers. Un echange sur un dossier
     * EST du contenu de dossier : y donner acces rouvrirait par la fenetre ce
     * que la portee globale ferme.
     */
    #[Test]
    public function l_administrateur_n_ecrit_pas_et_ne_lit_pas_le_fil(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('requests.messages.store', $this->demande), ['body' => 'Bonjour.'])
            ->assertNotFound();

        $this->assertSame(0, RequestMessage::count());
    }

    /** Pas d'interlocuteur sur un brouillon : personne n'a le dossier. */
    #[Test]
    public function on_n_ecrit_pas_sur_un_brouillon(): void
    {
        $brouillon = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'reason' => 'lost',
        ]);

        $this->actingAs($this->citoyen)
            ->post(route('requests.messages.store', $brouillon), ['body' => 'Bonjour.'])
            ->assertForbidden();
    }

    /* ------------------------------------------------------ le contenu */

    #[Test]
    public function un_message_vide_est_refuse(): void
    {
        $this->actingAs($this->citoyen)
            ->post(route('requests.messages.store', $this->demande), ['body' => '  '])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, RequestMessage::count());
    }

    /** La base refuse aussi, si la validation etait contournee. */
    #[Test]
    public function la_base_refuse_un_message_vide(): void
    {
        $this->expectException(QueryException::class);

        DB::table('request_messages')->insert([
            'request_id' => $this->demande->id,
            'author_id' => $this->citoyen->id,
            'author_role' => 'citizen',
            'body' => ' ',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** La base refuse un message d'administrateur, meme en SQL direct. */
    #[Test]
    public function la_base_refuse_un_message_d_administrateur(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(QueryException::class);

        DB::table('request_messages')->insert([
            'request_id' => $this->demande->id,
            'author_id' => $admin->id,
            'author_role' => 'admin',
            'body' => 'Contournement.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Le journal dit qu'un message a ete envoye, jamais ce qu'il contient.
     *
     * Un echange sur un dossier d'etat civil contient des donnees
     * personnelles ; le garde-fou n6 les interdit dans les journaux.
     */
    #[Test]
    public function le_journal_ne_porte_pas_le_contenu_du_message(): void
    {
        $texte = 'Mon fils est né à Douala et non à Yaoundé.';

        $this->actingAs($this->citoyen)
            ->post(route('requests.messages.store', $this->demande), ['body' => $texte]);

        $ligne = DB::table('audit_logs')->where('action', 'request.message_sent')->firstOrFail();

        $this->assertStringNotContainsString('Douala', json_encode($ligne, JSON_UNESCAPED_UNICODE));
        $this->assertNull($ligne->reason);
    }

    /* ------------------------------------------------------- l'affichage */

    #[Test]
    public function le_fil_s_affiche_des_deux_cotes(): void
    {
        $this->actingAs($this->citoyen)->post(route('requests.messages.store', $this->demande), [
            'body' => 'Un détail à signaler sur ma demande.',
        ]);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $this->demande))
            ->assertOk()
            ->assertSee('Un détail à signaler sur ma demande.');

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        $this->actingAs($this->officier)
            ->get(route('officer.verification.step', [$this->demande->refresh(), 5]))
            ->assertOk()
            ->assertSee('Un détail à signaler sur ma demande.');
    }
}
