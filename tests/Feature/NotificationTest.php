<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Notifications\RequestStatusChanged;
use App\Services\RequestTransitionService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notifications : ce qu'elles disent, et surtout ce qu'elles ne cassent pas.
 *
 * D-006 pose la regle : l'echec d'une notification ne doit jamais faire
 * echouer ni annuler une transition d'etat. Une file indisponible ne doit pas
 * annuler la decision d'un officier deja journalisee.
 */
class NotificationTest extends TestCase
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
            'national_id_number' => 'DEMO-600000001', 'completed_at' => now(),
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
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();
    }

    #[Test]
    public function chaque_transition_previent_le_demandeur(): void
    {
        Notification::fake();

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        Notification::assertSentTo(
            $this->citoyen,
            RequestStatusChanged::class,
            fn (RequestStatusChanged $n): bool => $n->to === RequestStatus::Pending
                && $n->from === RequestStatus::Draft
                && $n->reference === $this->demande->reference,
        );
    }

    /** Un retour du maire est une consigne de travail : l'officier l'apprend. */
    #[Test]
    public function un_retour_du_maire_previent_aussi_l_officier_qui_tient_le_dossier(): void
    {
        $maire = User::factory()->mayor($this->centre->commune)->create();
        $transitions = app(RequestTransitionService::class);

        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
        $transitions->transition($this->demande->refresh(), RequestStatus::Escalated, $this->officier, 'Doute sur la pièce.');

        Notification::fake();

        $transitions->transition($this->demande->refresh(), RequestStatus::UnderReview, $maire, 'Complément demandé.');

        Notification::assertSentTo($this->officier, RequestStatusChanged::class);
        Notification::assertSentTo($this->citoyen, RequestStatusChanged::class);
    }

    /** Une prise en charge ordinaire ne notifie pas l'officier lui-meme. */
    #[Test]
    public function une_prise_en_charge_ne_notifie_pas_l_officier(): void
    {
        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        Notification::fake();

        app(RequestTransitionService::class)
            ->transition($this->demande->refresh(), RequestStatus::UnderReview, $this->officier);

        Notification::assertNotSentTo($this->officier, RequestStatusChanged::class);
        Notification::assertSentTo($this->citoyen, RequestStatusChanged::class);
    }

    /**
     * LE test qui compte : une notification qui explose n'annule rien.
     *
     * On casse l'expedition elle-meme, pas le worker : c'est le cas le plus
     * dur, celui ou l'erreur remonte dans la meme requete que la transition.
     */
    #[Test]
    public function un_echec_de_notification_n_annule_pas_la_transition(): void
    {
        $this->app->extend(
            Dispatcher::class,
            fn () => new class implements Dispatcher
            {
                public function send($notifiables, $notification)
                {
                    throw new \RuntimeException('File de travaux injoignable.');
                }

                public function sendNow($notifiables, $notification, ?array $channels = null)
                {
                    throw new \RuntimeException('File de travaux injoignable.');
                }
            }
        );

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        $this->demande->refresh();

        $this->assertSame(RequestStatus::Pending, $this->demande->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $this->demande->id,
            'action' => 'request.draft_to_pending',
        ]);
    }

    /** Une transition refusee ne notifie personne. */
    #[Test]
    public function une_transition_refusee_ne_notifie_personne(): void
    {
        Notification::fake();

        try {
            app(RequestTransitionService::class)
                ->transition($this->demande, RequestStatus::Signed, $this->citoyen);
        } catch (\DomainException) {
            // Attendu.
        }

        Notification::assertNothingSent();
        $this->assertSame(RequestStatus::Draft, $this->demande->refresh()->status);
    }

    /**
     * Le motif ne quitte pas le systeme.
     *
     * Il est redige par un agent et peut mentionner des elements du dossier.
     * Le demandeur le lit connecte, sur la page de sa demande — pas dans un
     * courriel ni dans la table des notifications (garde-fou n6).
     */
    #[Test]
    public function ni_le_courriel_ni_la_notification_ne_portent_le_motif(): void
    {
        $motif = 'La photographie de la piece est illisible et le nom differe.';

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande->refresh(), RequestStatus::UnderReview, $this->officier);
        $transitions->transition($this->demande->refresh(), RequestStatus::Rejected, $this->officier, $motif);

        $notification = $this->citoyen->notifications()->latest('created_at')->firstOrFail();

        $this->assertStringNotContainsString($motif, json_encode($notification->data, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('illisible', json_encode($notification->data, JSON_UNESCAPED_UNICODE));

        $courriel = (new RequestStatusChanged(
            $this->demande->reference, $this->demande->id,
            RequestStatus::UnderReview, RequestStatus::Rejected,
        ))->toMail($this->citoyen);

        $this->assertStringNotContainsString($motif, json_encode($courriel->toArray(), JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function le_centre_de_notifications_n_affiche_que_les_siennes(): void
    {
        $autre = User::factory()->citizen()->create();

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        $this->actingAs($this->citoyen)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($this->demande->reference);

        $this->actingAs($autre)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee($this->demande->reference);
    }

    #[Test]
    public function marquer_comme_lu_ne_touche_que_ses_propres_notifications(): void
    {
        $autre = User::factory()->citizen()->create();
        $autre->notify(new RequestStatusChanged('PHX-AAAA-BBBB', 1, RequestStatus::Draft, RequestStatus::Pending));

        app(RequestTransitionService::class)
            ->transition($this->demande, RequestStatus::Pending, $this->citoyen);

        $this->actingAs($this->citoyen)->post(route('notifications.read'))->assertRedirect();

        $this->assertSame(0, $this->citoyen->unreadNotifications()->count());
        $this->assertSame(1, $autre->unreadNotifications()->count());
    }
}
