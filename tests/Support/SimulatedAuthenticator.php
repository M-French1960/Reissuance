<?php

declare(strict_types=1);

namespace Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;

/**
 * Un authentificateur WebAuthn simule, pour les tests.
 *
 * POURQUOI IL EXISTE. Verifier la signature par appareil sans appareil suppose
 * de fabriquer de vraies reponses WebAuthn : un objet d'attestation CBOR, des
 * donnees d'authentificateur au format binaire, et une signature ES256 valide.
 * Les bricoler dans chaque test aurait produit des tests illisibles et faux.
 *
 * CE QU'IL NE FAIT PAS : il ne simule pas la biometrie. Il pose le bit
 * « utilisateur verifie » comme le ferait un appareil apres un Face ID
 * reussi. Ce que les tests prouvent, c'est que le SERVEUR exige ce bit et
 * verifie la signature — pas que Face ID fonctionne, ce qu'aucun test
 * automatise ne peut etablir.
 */
final class SimulatedAuthenticator
{
    /** Drapeaux des donnees d'authentificateur. */
    private const FLAG_USER_PRESENT = 0x01;

    private const FLAG_USER_VERIFIED = 0x04;

    private const FLAG_ATTESTED_DATA = 0x40;

    public readonly string $credentialId;

    private \OpenSSLAsymmetricKey $cle;

    public function __construct(
        public readonly string $rpId = 'localhost',
        public readonly string $origin = 'http://localhost',
        private int $compteur = 0,
    ) {
        $this->credentialId = random_bytes(32);

        $cle = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($cle === false) {
            throw new \RuntimeException("Impossible d'engendrer la clé de l'authentificateur simulé.");
        }

        $this->cle = $cle;
    }

    /* ------------------------------------------------------------------ */
    /* Enrôlement */
    /* ------------------------------------------------------------------ */

    /** La reponse d'enrolement, au format que le navigateur envoie. */
    public function register(string $challenge): string
    {
        $clientData = $this->clientData('webauthn.create', $challenge);

        $authData = $this->authenticatorData(
            self::FLAG_USER_PRESENT | self::FLAG_USER_VERIFIED | self::FLAG_ATTESTED_DATA
        ).$this->attestedCredentialData();

        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return json_encode([
            'id' => $this->b64($this->credentialId),
            'rawId' => $this->b64($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->b64($clientData),
                'attestationObject' => $this->b64((string) $attestation),
            ],
            'clientExtensionResults' => new \stdClass,
        ], JSON_THROW_ON_ERROR);
    }

    /* ------------------------------------------------------------------ */
    /* Signature */
    /* ------------------------------------------------------------------ */

    /** La reponse de signature, au format que le navigateur envoie. */
    public function assert(string $challenge, string $userHandle): string
    {
        $this->compteur++;

        $clientData = $this->clientData('webauthn.get', $challenge);
        $authData = $this->authenticatorData(self::FLAG_USER_PRESENT | self::FLAG_USER_VERIFIED);

        $signature = '';
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->cle, OPENSSL_ALGO_SHA256);

        return json_encode([
            'id' => $this->b64($this->credentialId),
            'rawId' => $this->b64($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->b64($clientData),
                'authenticatorData' => $this->b64($authData),
                'signature' => $this->b64($signature),
                'userHandle' => $this->b64($userHandle),
            ],
            'clientExtensionResults' => new \stdClass,
        ], JSON_THROW_ON_ERROR);
    }

    /** Une signature faite avec une AUTRE cle : elle doit etre refusee. */
    public function assertWithWrongKey(string $challenge, string $userHandle): string
    {
        $autre = new self($this->rpId, $this->origin);
        $reponse = json_decode($autre->assert($challenge, $userHandle), true, flags: JSON_THROW_ON_ERROR);

        // On garde NOTRE identifiant, pour que le serveur retrouve bien
        // l'appareil enrole — et constate que la signature ne colle pas.
        $reponse['id'] = $this->b64($this->credentialId);
        $reponse['rawId'] = $this->b64($this->credentialId);

        return json_encode($reponse, JSON_THROW_ON_ERROR);
    }

    /* ------------------------------------------------------------------ */

    private function clientData(string $type, string $challenge): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $this->b64($challenge),
            'origin' => $this->origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
    }

    private function authenticatorData(int $drapeaux): string
    {
        return hash('sha256', $this->rpId, true)
            .chr($drapeaux)
            .pack('N', $this->compteur);
    }

    private function attestedCredentialData(): string
    {
        return str_repeat("\0", 16)                       // aaguid
            .pack('n', strlen($this->credentialId))       // longueur
            .$this->credentialId
            .$this->cosePublicKey();
    }

    /** La cle publique au format COSE, tel que l'attend la bibliotheque. */
    private function cosePublicKey(): string
    {
        $details = openssl_pkey_get_details($this->cle);

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new \RuntimeException('Clé EC illisible.');
        }

        $cle = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))      // kty : EC2
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))     // alg : ES256
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))     // crv : P-256
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)));

        return (string) $cle;
    }

    private function b64(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }
}
