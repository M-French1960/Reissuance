<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use App\Services\AttachmentRetention;
use App\Services\RequestTransitionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * La duree de vie d'une piece d'identite (D-094).
 *
 * `purge_after` existait depuis la premiere migration : declaree, castee,
 * INDEXEE — et jamais renseignee. Chaque piece d'identite et chaque selfie
 * etait donc conserve indefiniment derriere une colonne qui promettait
 * l'inverse. C'est la donnee la plus sensible du systeme, et c'etait la seule
 * dont rien ne bornait la duree.
 *
 * Ces tests tiennent le mecanisme, mais surtout SES DEUX REGLES DE SURETE. Un
 * mecanisme de suppression qui se trompe ne perd pas des donnees : il detruit
 * une verification en cours, et la piece ne se redemande pas — elle se
 * rephotographie, donc le demandeur recommence.
 */
class AttachmentRetentionTest extends TestCase
{
    private User $citoyen;

    private User $officier;

    private ReissuanceRequest $demande;

    private CivilStatusCenter $centre;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
        ]);
    }

    #[Test]
    public function sans_duree_configuree_une_piece_ne_porte_aucune_echeance(): void
    {
        config(['phoenix.uploads.retention_days' => null]);

        $piece = $this->televerse();

        $this->assertNull($piece->purge_after);
        $this->assertNull(AttachmentRetention::retentionDays());
    }

    #[Test]
    public function avec_une_duree_configuree_l_echeance_est_ecrite_au_depot(): void
    {
        config(['phoenix.uploads.retention_days' => 90]);

        $piece = $this->televerse();

        $this->assertNotNull($piece->purge_after);
        $this->assertSame(
            90,
            (int) round($piece->captured_at->diffInDays($piece->purge_after)),
            "L'echeance doit tomber 90 jours apres la capture."
        );
    }

    /**
     * Une duree nulle, negative ou vide ne borne rien, et ne doit surtout pas
     * se lire comme « purger tout de suite ».
     */
    #[Test]
    public function une_duree_absurde_est_traitee_comme_une_absence(): void
    {
        foreach (['', 0, -5] as $valeur) {
            config(['phoenix.uploads.retention_days' => $valeur]);

            $this->assertNull(
                AttachmentRetention::retentionDays(),
                'Une duree '.var_export($valeur, true).' doit valoir « non posee », jamais zero jour.'
            );
        }
    }

    #[Test]
    public function une_piece_echue_sur_un_dossier_termine_est_purgee(): void
    {
        config(['phoenix.uploads.retention_days' => 30]);

        $piece = $this->televerse();
        $chemin = $piece->path;
        $this->terminer(RequestStatus::Cancelled);
        $this->echoir($piece);

        $purges = $this->horsSession(fn () => AttachmentRetention::purge());

        $this->assertSame([$piece->id], $purges);
        $this->assertFalse(Storage::disk('private')->exists($chemin), 'Le fichier est reste sur le disque.');
        $this->assertNull(RequestAttachment::find($piece->id), 'La ligne est restee en base.');
    }

    /**
     * PREMIERE REGLE DE SURETE : le dossier vit, la piece reste.
     *
     * Une duree courte ne doit pas desarmer une verification en cours.
     * L'officier a besoin de la piece pour verifier, le maire pour decider.
     */
    #[Test]
    public function une_piece_echue_sur_un_dossier_en_cours_est_conservee(): void
    {
        config(['phoenix.uploads.retention_days' => 1]);

        $piece = $this->televerse();
        $this->demande->forceFill(['submitted_at' => now()])->save();
        app(RequestTransitionService::class)->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        app(RequestTransitionService::class)->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->echoir($piece);

        $purges = $this->horsSession(fn () => AttachmentRetention::purge());

        $this->assertSame([], $purges);
        $this->assertNotNull(RequestAttachment::find($piece->id), 'Une piece a ete purgee sous un dossier vivant.');

        $etat = $this->horsSession(fn () => AttachmentRetention::survey());
        $this->assertSame(1, $etat['dossiers_vivants'], 'La piece conservee doit etre signalee, pas passee sous silence.');
    }

    /**
     * SECONDE REGLE DE SURETE : sans echeance, jamais de purge.
     *
     * Les pieces deposees avant ce mecanisme ont `purge_after` a NULL. Deviner
     * leur echeance depuis `captured_at` reviendrait a supprimer des donnees
     * sur une regle que personne n'a posee.
     */
    #[Test]
    public function une_piece_sans_echeance_n_est_jamais_purgee(): void
    {
        config(['phoenix.uploads.retention_days' => null]);

        $piece = $this->televerse();
        $this->terminer(RequestStatus::Cancelled);

        $this->assertNull($piece->purge_after);

        // Meme une duree posee APRES coup ne rattrape pas cette piece.
        config(['phoenix.uploads.retention_days' => 1]);

        $purges = $this->horsSession(fn () => AttachmentRetention::purge());

        $this->assertSame([], $purges);
        $this->assertNotNull(RequestAttachment::find($piece->id));
        $this->assertSame(1, AttachmentRetention::unboundedCount());
    }

    /**
     * LA PURGE LAISSE UNE TRACE, ET LA TRACE NE PORTE PAS LE CONTENU.
     *
     * La tension de la question B6 — droit a l'effacement contre journal en
     * ajout seul — se resout ainsi : on efface le DOCUMENT, on garde le FAIT.
     * Aucun acteur n'est impute : personne n'a decide cette suppression, elle
     * applique un reglage, et inventer un administrateur serait une fausse
     * imputation.
     */
    #[Test]
    public function la_purge_est_journalisee_sans_acteur_et_sans_contenu(): void
    {
        config(['phoenix.uploads.retention_days' => 30]);

        $piece = $this->televerse();
        $chemin = $piece->path;
        $this->terminer(RequestStatus::Cancelled);
        $this->echoir($piece);

        $this->horsSession(fn () => AttachmentRetention::purge());

        $trace = AuditLog::where('action', AttachmentRetention::ACTION)
            ->where('auditable_id', $piece->id)
            ->firstOrFail();

        $this->assertNull($trace->actor_id);
        $this->assertNull($trace->actor_role);
        $this->assertSame('request_attachment', $trace->auditable_type);
        $this->assertStringNotContainsString($chemin, (string) json_encode($trace->toArray()));
    }

    /**
     * LA PURGE REFUSE DE TOURNER SOUS UN UTILISATEUR AUTHENTIFIE.
     *
     * `RequestVisibilityScope` restreindrait le balayage : la purge
     * tournerait, annoncerait un succes, et laisserait des pieces derriere
     * elle. Le 16 n'autorise pas un quatrieme contournement de portee, donc le
     * contexte est EXIGE, et l'echec est bruyant.
     */
    #[Test]
    public function la_purge_refuse_de_tourner_sous_une_portee_de_visibilite(): void
    {
        $this->actingAs($this->citoyen);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/portee de visibilite|authentifie/');

        AttachmentRetention::purge();
    }

    #[Test]
    public function la_commande_ne_supprime_rien_sans_apply(): void
    {
        config(['phoenix.uploads.retention_days' => 30]);

        $piece = $this->televerse();
        $this->terminer(RequestStatus::Cancelled);
        $this->echoir($piece);

        // Un vrai `php artisan` n'a aucune session ; le test, lui, en a ouvert
        // une pour televerser. La garde de portee a rattrape exactement cela,
        // et elle avait raison : on reproduit le contexte reel plutot que de
        // l'assouplir.
        auth()->forgetUser();

        $this->artisan('phoenix:purge-attachments')
            ->expectsOutputToContain('Recensement seul')
            ->assertExitCode(0);

        $this->assertNotNull(RequestAttachment::find($piece->id), 'Le recensement a supprime une piece.');

        $this->artisan('phoenix:purge-attachments', ['--apply' => true])->assertExitCode(0);

        $this->assertNull(RequestAttachment::find($piece->id));
    }

    #[Test]
    public function la_commande_dit_qu_aucune_duree_n_est_posee(): void
    {
        config(['phoenix.uploads.retention_days' => null]);

        $this->artisan('phoenix:purge-attachments')
            ->expectsOutputToContain('Aucune duree de conservation configuree')
            ->expectsOutputToContain('B3')
            ->assertExitCode(0);
    }

    /**
     * L'exposition doit etre DITE. Un exploitant ne decouvre pas dans un audit
     * que les pieces d'identite sont conservees pour toujours.
     */
    #[Test]
    public function le_controle_de_sante_signale_l_absence_de_duree_sans_declarer_la_panne(): void
    {
        config(['phoenix.uploads.retention_days' => null]);
        $this->televerse();

        $reponse = $this->actingAs(User::factory()->admin()->create())->getJson('/sante');

        // Signale, non bloquant : une question juridique ouverte n'est pas une
        // panne de disponibilite, et une sonde rouge en permanence cesse
        // d'etre lue.
        $reponse->assertOk()->assertJsonPath('status', 'ok');

        $sonde = collect($reponse->json('checks'))
            ->firstWhere('label', 'Identity document retention');

        $this->assertNotNull($sonde, 'La sonde de conservation a disparu.');
        $this->assertFalse($sonde['ok']);
        $this->assertTrue($sonde['advisory']);
        $this->assertStringContainsString('B3', $sonde['detail']);
    }

    #[Test]
    public function l_ecran_des_reglages_avertit_quand_la_duree_manque(): void
    {
        config(['phoenix.uploads.retention_days' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('PHOENIX_ATTACHMENT_RETENTION_DAYS')
            ->assertSee('kept indefinitely', false);
    }

    private function televerse(string $kind = 'selfie'): RequestAttachment
    {
        $this->actingAs($this->citoyen)->post(
            route('citizen.requests.attachments.store', $this->demande),
            ['kind' => $kind, 'file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]
        )->assertSessionHasNoErrors();

        return $this->demande->attachments()->where('kind', $kind)->firstOrFail();
    }

    /** Fait passer l'echeance, sans attendre quatre-vingt-dix jours. */
    private function echoir(RequestAttachment $piece): void
    {
        $piece->forceFill(['purge_after' => now()->subDay()])->save();
    }

    private function terminer(RequestStatus $etat): void
    {
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande, $etat, $this->citoyen);
        $this->demande->refresh();
    }

    /**
     * La purge exige un contexte sans utilisateur authentifie, comme en
     * console. Les tests, eux, ont ouvert une session pour televerser.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function horsSession(callable $action): mixed
    {
        auth()->forgetUser();

        return $action();
    }
}
