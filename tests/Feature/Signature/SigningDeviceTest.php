<?php

declare(strict_types=1);

namespace Tests\Feature\Signature;

use App\Models\CivilStatusCenter;
use App\Models\SigningDevice;
use App\Models\User;
use App\Services\Webauthn\SigningDeviceService;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SimulatedAuthenticator;
use Tests\TestCase;

/**
 * Enrolement et signature par appareil — WebAuthn (D-070).
 *
 * CE QUE CES TESTS PROUVENT : que le serveur exige une signature
 * cryptographique valide, faite par une cle qu'il n'a jamais eue, liee a ce
 * compte et a ce defi.
 *
 * CE QU'ILS NE PROUVENT PAS, et il faut le dire : que Face ID fonctionne.
 * L'authentificateur simule pose le bit « utilisateur verifie » comme le
 * ferait un appareil apres une biometrie reussie ; aucun test automatise ne
 * peut etablir qu'un vrai capteur reconnait un vrai visage. Ce qui est
 * verifie, c'est que le serveur EXIGE ce bit et refuse tout le reste.
 */
class SigningDeviceTest extends TestCase
{
    private User $maire;

    private SigningDeviceService $service;

    private SimulatedAuthenticator $appareil;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost', 'phoenix.security.webauthn_rp_id' => 'localhost']);

        $centre = CivilStatusCenter::factory()->create();
        $this->maire = User::factory()->mayor($centre->commune)->create();
        $this->service = app(SigningDeviceService::class);
        $this->appareil = new SimulatedAuthenticator('localhost', 'http://localhost');
    }

    private function enrole(?SimulatedAuthenticator $appareil = null): SigningDevice
    {
        $appareil ??= $this->appareil;
        $options = $this->service->creationOptions($this->maire);

        return $this->service->register(
            $this->maire,
            $appareil->register($options->challenge),
            $options,
            'Téléphone du maire',
            'localhost',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Enrôlement */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function un_appareil_s_enrole_et_sa_cle_publique_est_conservee(): void
    {
        $appareil = $this->enrole();

        $this->assertSame($this->maire->id, $appareil->user_id);
        $this->assertSame('Téléphone du maire', $appareil->label);
        $this->assertNotSame('', $appareil->public_key);
        $this->assertDatabaseCount('signing_devices', 1);
    }

    /**
     * AUCUNE DONNEE BIOMETRIQUE N'EST CONSERVEE.
     *
     * C'est la garantie de fond de ce dispositif, et elle doit etre verifiee,
     * pas affirmee. La table ne porte qu'une cle publique, un identifiant et
     * un compteur.
     */
    #[Test]
    public function aucune_donnee_biometrique_n_est_conservee(): void
    {
        $this->enrole();

        $colonnes = array_keys((array) SigningDevice::first()->getAttributes());

        foreach (['image', 'photo', 'face', 'template', 'biometric', 'fingerprint', 'gabarit'] as $interdit) {
            foreach ($colonnes as $colonne) {
                $this->assertStringNotContainsString(
                    $interdit,
                    strtolower($colonne),
                    "La colonne « {$colonne} » suggère une donnée biométrique conservée."
                );
            }
        }
    }

    #[Test]
    public function le_defi_d_enrolement_change_a_chaque_fois(): void
    {
        // Un défi rejouable rendrait une réponse capturée réutilisable.
        $this->assertNotSame(
            $this->service->creationOptions($this->maire)->challenge,
            $this->service->creationOptions($this->maire)->challenge
        );
    }

    #[Test]
    public function un_meme_appareil_ne_s_enrole_pas_deux_fois(): void
    {
        $this->enrole();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('déjà enrôlé');

        $this->enrole();
    }

    #[Test]
    public function une_reponse_illisible_est_refusee(): void
    {
        $options = $this->service->creationOptions($this->maire);

        $this->expectException(DomainException::class);

        $this->service->register($this->maire, '{ ceci n est pas du json', $options, 'Essai', 'localhost');
    }

    /* ------------------------------------------------------------------ */
    /* Signature */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function un_appareil_enrole_signe(): void
    {
        $enrole = $this->enrole();

        $options = $this->service->requestOptions($this->maire);
        $reponse = $this->appareil->assert($options->challenge, $this->handle());

        $utilise = $this->service->verify($this->maire, $reponse, $options, 'localhost');

        $this->assertSame($enrole->id, $utilise->id);
        $this->assertNotNull($utilise->fresh()->last_used_at);
    }

    /** LE test : une signature faite avec une autre clé est refusée. */
    #[Test]
    public function une_signature_faite_avec_une_autre_cle_est_refusee(): void
    {
        $this->enrole();

        $options = $this->service->requestOptions($this->maire);
        $reponse = $this->appareil->assertWithWrongKey($options->challenge, $this->handle());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('refusée');

        $this->service->verify($this->maire, $reponse, $options, 'localhost');
    }

    /** Une signature portant sur un autre défi est refusée : pas de rejeu. */
    #[Test]
    public function une_signature_sur_un_autre_defi_est_refusee(): void
    {
        $this->enrole();

        $options = $this->service->requestOptions($this->maire);
        $reponse = $this->appareil->assert(random_bytes(32), $this->handle());

        $this->expectException(DomainException::class);

        $this->service->verify($this->maire, $reponse, $options, 'localhost');
    }

    /** Une signature obtenue depuis un autre site est refusée. */
    #[Test]
    public function une_signature_d_une_autre_origine_est_refusee(): void
    {
        $this->enrole();

        $options = $this->service->requestOptions($this->maire);

        $ailleurs = new SimulatedAuthenticator('localhost', 'https://mairie-de-yaounde.example');
        $reponse = $ailleurs->assert($options->challenge, $this->handle());

        $this->expectException(DomainException::class);

        $this->service->verify($this->maire, $reponse, $options, 'localhost');
    }

    /** L'appareil d'un autre maire ne signe pas pour celui-ci. */
    #[Test]
    public function l_appareil_d_un_autre_maire_est_refuse(): void
    {
        $this->enrole();

        $autre = User::factory()->mayor(CivilStatusCenter::factory()->create()->commune)->create();

        $options = $this->service->requestOptions($this->maire);
        $reponse = $this->appareil->assert($options->challenge, $this->handle());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('enrôlé sur ce compte');

        // L'autre maire présente une signature faite par l'appareil du premier.
        $this->service->verify($autre, $reponse, $options, 'localhost');
    }

    #[Test]
    public function sans_appareil_enrole_la_demande_de_signature_est_refusee(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Aucun appareil n'est enrôlé");

        $this->service->requestOptions($this->maire);
    }

    /* ------------------------------------------------------------------ */

    private function handle(): string
    {
        return hash_hmac(
            'sha256',
            'phoenix-webauthn-user:'.$this->maire->id,
            (string) config('app.key'),
            true
        );
    }
}
