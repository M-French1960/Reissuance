<?php

declare(strict_types=1);

namespace Tests\Feature\Signature;

use App\Integrations\Real\Docusign\DocusignClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Client Docusign, verifie contre un SERVEUR SIMULE.
 *
 * A DIRE SANS AMBIGUITE, comme pour HR-Skills Pay : aucun appel n'a ete fait
 * contre le service reel. Le contrat est releve dans le code publie par
 * Docusign (SDK officiel et exemples), et ces tests verifient que le client
 * s'y conforme — pas que Docusign se conforme a son propre SDK. La reprise
 * contre le bac a sable, avec de vrais identifiants, reste a faire (D-066).
 *
 * LA CLE PRIVEE EST ENGENDREE A L'EXECUTION. Aucun materiau cryptographique
 * n'entre dans le depot, meme de test. Elle sert a verifier ce qui compte
 * vraiment : que l'assertion JWT est REELLEMENT signee, et verifiable avec la
 * cle publique correspondante — pas seulement qu'une chaine a trois points est
 * envoyee.
 */
class DocusignClientTest extends TestCase
{
    private const OAUTH = 'account-d.docusign.test';

    private const BASE = 'https://demo.docusign.test';

    private const COMPTE = '1234-abcd';

    /** Engendree une fois : 2048 bits par test rendrait la suite inutilement lente. */
    private static ?string $privateKey = null;

    private static ?string $publicKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        if (self::$privateKey === null) {
            $paire = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            $this->assertNotFalse($paire, "Impossible d'engendrer la paire de clés de test.");

            openssl_pkey_export($paire, $pem);
            self::$privateKey = $pem;
            self::$publicKey = (string) openssl_pkey_get_details($paire)['key'];
        }

        config([
            'phoenix.signature.docusign.oauth_base_path' => self::OAUTH,
            'phoenix.signature.docusign.integration_key' => 'cle-integration-0001',
            'phoenix.signature.docusign.user_id' => 'utilisateur-impersonne-0001',
            'phoenix.signature.docusign.private_key' => self::$privateKey,
            'phoenix.signature.docusign.account_id' => '',
            'phoenix.signature.docusign.seal_name' => 'sceau-0001',
            'phoenix.signature.docusign.timeout' => 5,
        ]);
    }

    private function client(): DocusignClient
    {
        return new DocusignClient;
    }

    /**
     * Le serveur simule, dans son etat nominal.
     *
     * @param  array<string, mixed>  $remplacements
     */
    private function fakeServer(array $remplacements = []): void
    {
        Http::fake($remplacements + [
            'https://'.self::OAUTH.'/oauth/token' => Http::response([
                'access_token' => 'JETON-ACCES', 'expires_in' => 3600, 'token_type' => 'Bearer',
            ]),
            'https://'.self::OAUTH.'/oauth/userinfo' => Http::response([
                'sub' => 'utilisateur-impersonne-0001',
                'accounts' => [
                    ['account_id' => 'autre-compte', 'is_default' => 'false',
                        'base_uri' => 'https://eu.docusign.test'],
                    ['account_id' => self::COMPTE, 'is_default' => 'true',
                        'base_uri' => self::BASE],
                ],
            ]),
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/seals' => Http::response([
                'seals' => [
                    ['sealName' => 'sceau-0001', 'sealDisplayName' => 'Commune'],
                    ['sealName' => 'sceau-0002', 'sealDisplayName' => 'Autre'],
                ],
            ]),
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes' => Http::response([
                'envelopeId' => 'env-0001', 'status' => 'sent',
            ], 201),
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes/env-0001' => Http::response([
                'envelopeId' => 'env-0001', 'status' => 'completed',
            ]),
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes/env-0001/documents/*' => Http::response(
                "%PDF-1.4\nacte signé\n%%EOF"
            ),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Authentification */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function l_assertion_est_reellement_signee_avec_la_cle_privee(): void
    {
        $this->fakeServer();

        $this->assertSame('JETON-ACCES', $this->client()->accessToken());

        Http::assertSent(function (Request $requete): bool {
            if ($requete->url() !== 'https://'.self::OAUTH.'/oauth/token') {
                return false;
            }

            $this->assertSame(
                'urn:ietf:params:oauth:grant-type:jwt-bearer',
                $requete['grant_type'],
                'Le type de permission du flux JWT Grant est fixé par Docusign.'
            );

            [$entete, $charge, $signature] = explode('.', (string) $requete['assertion']);

            // La signature, verifiee pour de bon : une assertion mal signee
            // serait refusee par Docusign, et le test ne le verrait pas si on
            // se contentait de compter les points.
            $verifiee = openssl_verify(
                "{$entete}.{$charge}",
                (string) base64_decode(strtr($signature, '-_', '+/'), true),
                (string) self::$publicKey,
                OPENSSL_ALGO_SHA256,
            );

            $this->assertSame(1, $verifiee, "L'assertion JWT n'est pas signée par la clé privée.");

            $this->assertSame(
                ['alg' => 'RS256', 'typ' => 'JWT'],
                $this->decode($entete),
            );

            $claim = $this->decode($charge);

            $this->assertSame('cle-integration-0001', $claim['iss']);
            $this->assertSame('utilisateur-impersonne-0001', $claim['sub']);
            // `aud` est l'hôte nu, sans schéma : Docusign compare à l'identique.
            $this->assertSame(self::OAUTH, $claim['aud']);
            $this->assertSame('signature impersonation', $claim['scope']);
            $this->assertLessThanOrEqual(3600, $claim['exp'] - $claim['iat']);

            return true;
        });
    }

    #[Test]
    public function le_schema_est_retire_de_l_hote_avant_d_en_faire_le_aud(): void
    {
        config(['phoenix.signature.docusign.oauth_base_path' => 'https://'.self::OAUTH.'/']);

        $this->fakeServer();
        $this->client()->accessToken();

        Http::assertSent(function (Request $requete): bool {
            if (! str_ends_with($requete->url(), '/oauth/token')) {
                return false;
            }

            [, $charge] = explode('.', (string) $requete['assertion']);

            return $this->decode($charge)['aud'] === self::OAUTH;
        });
    }

    #[Test]
    public function le_jeton_est_garde_en_cache_le_temps_de_sa_validite(): void
    {
        $this->fakeServer();

        $client = $this->client();
        $client->accessToken();
        $client->accessToken();

        Http::assertSentCount(1);
    }

    #[Test]
    public function un_jeton_sans_duree_annoncee_n_est_pas_garde(): void
    {
        // Sans `expires_in`, on ignore quand le jeton meurt. Le garder, c'est
        // risquer un 401 au milieu d'une opération déjà partie.
        $this->fakeServer([
            'https://'.self::OAUTH.'/oauth/token' => Http::response(['access_token' => 'JETON-ACCES']),
        ]);

        $client = $this->client();
        $client->accessToken();
        $client->accessToken();

        Http::assertSentCount(2);
    }

    #[Test]
    public function le_consentement_manquant_est_nomme_pour_ce_qu_il_est(): void
    {
        $this->fakeServer([
            'https://'.self::OAUTH.'/oauth/token' => Http::response(
                ['error' => 'consent_required'], 400
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('consentement');

        $this->client()->accessToken();
    }

    #[Test]
    public function le_corps_d_une_erreur_d_authentification_n_est_jamais_recopie(): void
    {
        // Docusign renvoie l'assertion en écho dans certaines erreurs. Elle
        // porte une signature valide de notre clé privée : la recopier dans un
        // message d'exception la ferait entrer dans les journaux.
        $this->fakeServer([
            'https://'.self::OAUTH.'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'assertion' => 'eyJhbGciOiJSUzI1NiJ9.CHARGE-UTILE-SECRETE.SIGNATURE',
            ], 400),
        ]);

        try {
            $this->client()->accessToken();
            $this->fail("L'authentification refusée aurait dû lever.");
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('CHARGE-UTILE-SECRETE', $e->getMessage());
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Clé privée */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function une_cle_rangee_dans_le_depot_est_refusee(): void
    {
        // Le refus porte sur l'EMPLACEMENT, avant toute lecture : on désigne
        // donc un fichier du dépôt qui n'est pas une clé.
        config(['phoenix.signature.docusign.private_key' => base_path('composer.json')]);

        $this->fakeServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dans le dépôt');

        $this->client()->accessToken();
    }

    #[Test]
    public function une_cle_rangee_hors_du_depot_est_lue(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'phoenix-docusign-');
        file_put_contents($chemin, (string) self::$privateKey);

        try {
            config(['phoenix.signature.docusign.private_key' => $chemin]);

            $this->fakeServer();

            $this->assertSame('JETON-ACCES', $this->client()->accessToken());
        } finally {
            @unlink($chemin);
        }
    }

    #[Test]
    public function une_cle_absente_est_dite(): void
    {
        config(['phoenix.signature.docusign.private_key' => '']);

        $this->fakeServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHOENIX_DOCUSIGN_PRIVATE_KEY');

        $this->client()->accessToken();
    }

    /* ------------------------------------------------------------------ */
    /* Compte */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function le_compte_par_defaut_est_retenu_a_defaut_de_compte_configure(): void
    {
        $this->fakeServer();

        $compte = $this->client()->account();

        $this->assertSame(self::COMPTE, $compte->accountId);
        $this->assertSame(self::BASE, $compte->baseUri);
        // Le SDK officiel suffixe `/restapi` au domaine rendu par userinfo.
        $this->assertSame(self::BASE.'/restapi', $compte->restApi());
    }

    #[Test]
    public function le_compte_configure_prime_sur_le_compte_par_defaut(): void
    {
        config(['phoenix.signature.docusign.account_id' => 'autre-compte']);

        $this->fakeServer();

        $compte = $this->client()->account();

        $this->assertSame('autre-compte', $compte->accountId);
        $this->assertSame('https://eu.docusign.test', $compte->baseUri);
    }

    #[Test]
    public function un_compte_configure_inaccessible_est_refuse(): void
    {
        // Retomber sur le compte par défaut signerait des actes sur un compte
        // que personne n'a choisi.
        config(['phoenix.signature.docusign.account_id' => 'compte-inexistant']);

        $this->fakeServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas accessible");

        $this->client()->account();
    }

    #[Test]
    public function l_absence_de_compte_par_defaut_exige_un_choix_explicite(): void
    {
        $this->fakeServer([
            'https://'.self::OAUTH.'/oauth/userinfo' => Http::response([
                'accounts' => [
                    ['account_id' => 'a', 'is_default' => 'false', 'base_uri' => self::BASE],
                    ['account_id' => 'b', 'is_default' => 'false', 'base_uri' => self::BASE],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHOENIX_DOCUSIGN_ACCOUNT_ID');

        $this->client()->account();
    }

    /* ------------------------------------------------------------------ */
    /* Sceaux */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function les_sceaux_du_compte_sont_lus(): void
    {
        $this->fakeServer();

        $this->assertSame(['sceau-0001', 'sceau-0002'], $this->client()->availableSeals());
    }

    #[Test]
    public function un_compte_sans_sceau_rend_une_liste_vide(): void
    {
        // Vide, et non « erreur » : un compte peut exister sans sceau
        // provisionné. C'est la tentative de signature qui doit échouer, pas
        // le diagnostic.
        $this->fakeServer([
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/seals' => Http::response(['seals' => []]),
        ]);

        $this->assertSame([], $this->client()->availableSeals());
    }

    /* ------------------------------------------------------------------ */
    /* Enveloppes */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function l_enveloppe_scellee_ne_porte_aucun_destinataire_humain(): void
    {
        $definition = $this->client()->sealedEnvelopeDefinition(
            '%PDF-1.4 projet', 'sceau-0001', ['reference' => 'PHX-2026-0001']
        );

        $this->assertSame('sent', $definition['status']);
        $this->assertArrayNotHasKey(
            'signers',
            $definition['recipients'],
            'Un destinataire humain rendrait la signature dépendante d’un clic hors de PHOENIX.'
        );

        $sceau = $definition['recipients']['seals'][0];
        $this->assertSame('sceau-0001', $sceau['recipientSignatureProviders'][0]['sealName']);

        $document = $definition['documents'][0];
        $this->assertSame('%PDF-1.4 projet', base64_decode($document['documentBase64'], true));
        $this->assertSame('pdf', $document['fileExtension']);
        $this->assertStringContainsString('PHX-2026-0001', $document['name']);
    }

    #[Test]
    public function la_creation_d_enveloppe_rend_son_identifiant(): void
    {
        $this->fakeServer();

        $this->assertSame('env-0001', $this->client()->createEnvelope(['status' => 'sent']));

        Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
            && $r->url() === self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes'
            && $r->hasHeader('Authorization', 'Bearer JETON-ACCES'));
    }

    #[Test]
    public function une_enveloppe_acceptee_sans_identifiant_est_une_erreur(): void
    {
        // Cas dangereux : l'enveloppe existe peut-être chez Docusign. Réessayer
        // à l'aveugle en créerait une seconde.
        $this->fakeServer([
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes' => Http::response(['status' => 'sent'], 201),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ne pas réessayer');

        $this->client()->createEnvelope(['status' => 'sent']);
    }

    #[Test]
    public function le_statut_de_l_enveloppe_est_rendu_en_minuscules(): void
    {
        $this->fakeServer();

        $this->assertSame('completed', $this->client()->envelopeStatus('env-0001'));
    }

    #[Test]
    public function une_enveloppe_sans_statut_n_est_jamais_reputee_signee(): void
    {
        $this->fakeServer([
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes/env-0001' => Http::response(
                ['envelopeId' => 'env-0001']
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sans statut');

        $this->client()->envelopeStatus('env-0001');
    }

    #[Test]
    public function le_document_signe_est_rendu_tel_quel(): void
    {
        $this->fakeServer();

        $this->assertStringStartsWith('%PDF-', $this->client()->downloadDocument('env-0001'));
    }

    #[Test]
    public function un_telechargement_qui_n_est_pas_un_pdf_est_refuse(): void
    {
        // Un corps d'erreur rendu avec un code 200 serait sinon enregistré
        // dans le dossier du citoyen comme s'il s'agissait de son acte.
        $this->fakeServer([
            self::BASE.'/restapi/v2.1/accounts/'.self::COMPTE.'/envelopes/env-0001/documents/*' => Http::response(
                '{"errorCode":"ENVELOPE_DOES_NOT_EXIST"}'
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas un PDF");

        $this->client()->downloadDocument('env-0001');
    }

    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function decode(string $segment): array
    {
        return (array) json_decode(
            (string) base64_decode(strtr($segment, '-_', '+/'), true),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
