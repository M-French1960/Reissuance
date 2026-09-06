<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests R2 et R15 de docs/PERMISSIONS.md, enfin implementables.
 *
 * Les pieces d'identite sont les donnees les plus sensibles du systeme.
 */
class IdentityDocumentTest extends TestCase
{
    private User $citoyen;

    private ReissuanceRequest $demande;

    private CivilStatusCenter $centre;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
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

    private function televerse(string $kind = 'selfie'): RequestAttachment
    {
        $this->actingAs($this->citoyen)->post(
            route('citizen.requests.attachments.store', $this->demande),
            ['kind' => $kind, 'file' => UploadedFile::fake()->image('photo.jpg', 800, 600)]
        )->assertSessionHasNoErrors();

        return $this->demande->attachments()->where('kind', $kind)->firstOrFail();
    }

    #[Test]
    public function le_fichier_est_ecrit_hors_de_la_racine_web(): void
    {
        $piece = $this->televerse();

        $this->assertSame('private', $piece->disk);
        Storage::disk('private')->assertExists($piece->path);

        // Rien ne doit apparaitre dans public/.
        $this->assertFalse(
            file_exists(public_path($piece->path)),
            'Une pièce ne doit jamais être accessible depuis la racine web.'
        );
    }

    /**
     * Un chemin devinable annulerait la protection : il suffirait de connaitre
     * l'identite d'une personne pour construire l'URL de sa piece.
     */
    #[Test]
    public function le_chemin_du_fichier_est_opaque(): void
    {
        $this->citoyen->profile->update(['national_id_number' => 'DEMO-987654321']);
        $piece = $this->televerse();

        foreach (['DE TEST', 'Personne', '987654321', $this->citoyen->email, 'photo.jpg'] as $fuite) {
            $this->assertStringNotContainsStringIgnoringCase(
                $fuite,
                $piece->path,
                "Le chemin ne doit rien laisser deviner : « {$fuite} » y figure."
            );
        }

        $this->assertMatchesRegularExpression(
            '#^attachments/(selfie|id_document)/[0-9a-f-]{36}$#',
            $piece->path
        );
    }

    /** R2 — un citoyen ne peut pas atteindre la piece d'un autre. */
    #[Test]
    public function r2_un_citoyen_ne_peut_pas_lire_la_piece_d_un_autre(): void
    {
        $piece = $this->televerse();
        $intrus = User::factory()->citizen()->create();

        $this->actingAs($intrus)
            ->get(route('citizen.attachments.show', $piece))
            ->assertForbidden();
    }

    #[Test]
    public function r2bis_un_visiteur_non_connecte_ne_peut_pas_lire_une_piece(): void
    {
        $piece = $this->televerse();

        // actingAs() reste actif pour tout le reste du test : sans cet oubli
        // de garde, la requete suivante serait encore authentifiee et le test
        // passerait sans rien prouver.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get(route('citizen.attachments.show', $piece))->assertRedirect(route('login'));
    }

    /** R9 — l'administrateur n'accede a aucune piece, sans exception. */
    #[Test]
    public function r9_l_administrateur_ne_peut_pas_lire_une_piece(): void
    {
        $piece = $this->televerse();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('citizen.attachments.show', $piece))
            ->assertForbidden();
    }

    #[Test]
    public function un_officier_d_un_autre_centre_ne_peut_pas_lire_la_piece(): void
    {
        $piece = $this->televerse();
        $autreCentre = CivilStatusCenter::factory()->create();

        $this->actingAs(User::factory()->officer($autreCentre)->create())
            ->get(route('citizen.attachments.show', $piece))
            ->assertForbidden();
    }

    /** R15 — toute consultation accordee est journalisee. */
    #[Test]
    public function r15_la_consultation_est_journalisee(): void
    {
        $piece = $this->televerse();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.attachments.show', $piece))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->citoyen->id,
            'action' => 'identity.selfie_viewed',
            'auditable_type' => 'request_attachment',
            'auditable_id' => $piece->id,
        ]);
    }

    #[Test]
    public function un_refus_n_ecrit_aucune_ligne_de_consultation(): void
    {
        $piece = $this->televerse();
        $intrus = User::factory()->citizen()->create();

        $this->actingAs($intrus)->get(route('citizen.attachments.show', $piece));

        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $intrus->id,
            'action' => 'identity.selfie_viewed',
        ]);
    }

    #[Test]
    public function la_piece_n_est_jamais_mise_en_cache(): void
    {
        $piece = $this->televerse();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.attachments.show', $piece))
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Symfony reordonne les directives : on verifie leur presence, pas
        // l'ordre dans lequel elles sont ecrites.
        $cacheControl = $this->actingAs($this->citoyen)
            ->get(route('citizen.attachments.show', $piece))
            ->headers->get('Cache-Control');

        foreach (['no-store', 'private', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
    }

    /**
     * Le type est relu depuis le CONTENU, jamais depuis l'extension ni
     * l'en-tete envoye par le navigateur, tous deux sous controle du client.
     *
     * Ce test emploie un vrai fichier temporaire, et non
     * UploadedFile::fake()->createWithContent() : ce dernier deduit le type
     * MIME de l'extension. Un script PHP nomme photo.jpg y serait annonce
     * comme image/jpeg, et le test passerait sans rien verifier.
     */
    #[Test]
    #[DataProvider('fichiersRefuses')]
    public function un_fichier_non_image_est_refuse(string $nom, string $contenu): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'phoenix-test').'-'.$nom;
        file_put_contents($chemin, $contenu);

        try {
            $this->actingAs($this->citoyen)->post(
                route('citizen.requests.attachments.store', $this->demande),
                ['kind' => 'selfie', 'file' => new UploadedFile($chemin, $nom, null, null, true)]
            )->assertSessionHasErrors('file');

            $this->assertSame(0, $this->demande->attachments()->count());
        } finally {
            @unlink($chemin);
        }
    }

    /** Contre-epreuve : une vraie image, elle, doit passer. */
    #[Test]
    public function une_vraie_image_est_acceptee(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'phoenix-test').'.jpg';
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $chemin);
        imagedestroy($image);

        try {
            $this->actingAs($this->citoyen)->post(
                route('citizen.requests.attachments.store', $this->demande),
                ['kind' => 'selfie', 'file' => new UploadedFile($chemin, 'photo.jpg', null, null, true)]
            )->assertSessionHasNoErrors();

            $this->assertSame(1, $this->demande->attachments()->count());
            $this->assertSame('image/jpeg', $this->demande->attachments()->first()->mime_type);
        } finally {
            @unlink($chemin);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function fichiersRefuses(): iterable
    {
        yield 'script PHP deguise en jpg' => ['photo.jpg', '<?php echo "compromis"; ?>'];
        yield 'document texte' => ['document.txt', 'du texte ordinaire'];
        yield 'archive' => ['archive.zip', "PK\x03\x04 contenu"];
    }

    #[Test]
    public function un_fichier_trop_volumineux_est_refuse(): void
    {
        $maxKo = (int) (config('phoenix.uploads.max_bytes') / 1024);

        $this->actingAs($this->citoyen)->post(
            route('citizen.requests.attachments.store', $this->demande),
            ['kind' => 'selfie', 'file' => UploadedFile::fake()->image('grande.jpg')->size($maxKo + 512)]
        )->assertSessionHasErrors('file');
    }

    /** Reprendre une photo remplace la precedente, sans laisser d'orphelin. */
    #[Test]
    public function reprendre_une_photo_remplace_la_precedente(): void
    {
        $premiere = $this->televerse();
        $seconde = $this->televerse();

        $this->assertNotSame($premiere->id, $seconde->id);
        $this->assertSame(1, $this->demande->attachments()->where('kind', 'selfie')->count());
        Storage::disk('private')->assertMissing($premiere->path);
        Storage::disk('private')->assertExists($seconde->path);
    }

    /** Une empreinte differente signale une alteration du fichier. */
    #[Test]
    public function une_piece_alteree_n_est_pas_servie(): void
    {
        $piece = $this->televerse();

        Storage::disk('private')->put($piece->path, 'contenu remplace');

        $this->actingAs($this->citoyen)
            ->get(route('citizen.attachments.show', $piece))
            ->assertServerError();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $piece->id,
            'action' => 'identity.checksum_mismatch',
        ]);
    }
}
