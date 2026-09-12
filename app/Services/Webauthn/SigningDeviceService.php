<?php

declare(strict_types=1);

namespace App\Services\Webauthn;

use App\Models\SigningDevice;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Enrolement et verification d'un appareil de signature — WebAuthn (D-070).
 *
 * CE QUE CE DISPOSITIF APPORTE, et que le code TOTP seul n'apportait pas : la
 * cle qui scelle l'acte est CELLE DU MAIRE, gardee dans l'element securise de
 * son appareil. Le serveur ne l'a jamais eue et ne peut pas signer a sa place.
 * Le code TOTP prouvait sa presence ; la cle prouve que c'est lui.
 *
 * CE QUE LE SERVEUR NE VOIT JAMAIS : le visage, l'empreinte, ou quelque autre
 * donnee biometrique. La biometrie deverrouille LOCALEMENT la cle privee ; il
 * n'en sort qu'une signature. C'est la difference de fond avec la comparaison
 * faciale du demandeur (docs/BIOMETRIE.md), qui envoie des photographies a un
 * service.
 *
 * CONTRAINTE A CONNAITRE : WebAuthn exige un contexte securise — TLS, ou
 * `localhost`. En production locale, sans TLS, ce dispositif est indisponible
 * et le code TOTP reste le seul moyen de signer. C'est aussi le repli en cas
 * d'appareil perdu : une commune ne doit pas cesser de delivrer des actes
 * parce qu'un telephone est tombe dans un seau d'eau.
 */
final class SigningDeviceService
{
    /** Duree de validite d'un defi, en secondes. */
    private const CHALLENGE_TTL = 300;

    /* ------------------------------------------------------------------ */
    /* Enrôlement */
    /* ------------------------------------------------------------------ */

    /** Les options a remettre au navigateur pour enroler un appareil. */
    public function creationOptions(User $officiel): PublicKeyCredentialCreationOptions
    {
        $dejaEnroles = SigningDevice::query()
            ->where('user_id', $officiel->id)
            ->pluck('credential_id')
            ->map(fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $this->decode($id),
            ))
            ->all();

        return PublicKeyCredentialCreationOptions::create(
            rp: $this->relyingParty(),
            user: $this->userEntity($officiel),
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create('public-key', -7),   // ES256
                PublicKeyCredentialParameters::create('public-key', -257), // RS256
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                // L'appareil lui-meme, pas une cle USB : c'est Face ID,
                // Windows Hello ou l'empreinte du telephone qu'on vise.
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                // LA VERIFICATION DE L'UTILISATEUR EST EXIGEE, pas seulement
                // sa presence : sans cela, un appareil deverrouille suffirait,
                // et on retomberait sur le defaut qu'on corrige.
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            // `none` : on ne cherche pas a etablir la provenance de l'appareil,
            // seulement a lier une cle a un compte. Demander une attestation
            // ferait remonter au serveur un identifiant de modele sans rien
            // apporter a la garantie recherchee.
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $dejaEnroles,
        );
    }

    /**
     * Verifie la reponse d'enrolement et enregistre l'appareil.
     *
     * @throws DomainException
     */
    public function register(
        User $officiel,
        string $reponseJson,
        PublicKeyCredentialCreationOptions $options,
        string $label,
        string $host,
    ): SigningDevice {
        $credential = $this->deserialize($reponseJson);
        $reponse = $credential->response;

        if (! $reponse instanceof AuthenticatorAttestationResponse) {
            throw new DomainException("La réponse de l'appareil n'est pas une réponse d'enrôlement.");
        }

        try {
            $record = AuthenticatorAttestationResponseValidator::create(
                $this->ceremonies($host)->creationCeremony()
            )->check($reponse, $options, $host);
        } catch (Throwable $e) {
            throw new DomainException(
                "L'appareil n'a pas pu être enrôlé : ".$e->getMessage()
            );
        }

        $identifiant = $this->encode($record->publicKeyCredentialId);

        if (SigningDevice::query()->where('credential_id', $identifiant)->exists()) {
            throw new DomainException('Cet appareil est déjà enrôlé.');
        }

        return SigningDevice::create([
            'user_id' => $officiel->id,
            'credential_id' => $identifiant,
            'public_key' => $this->encode($record->credentialPublicKey),
            'label' => $label,
            'sign_count' => $record->counter,
            'aaguid' => $record->aaguid->__toString(),
            'transports' => $record->transports,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Signature */
    /* ------------------------------------------------------------------ */

    /** Les options a remettre au navigateur pour signer. */
    public function requestOptions(User $officiel): PublicKeyCredentialRequestOptions
    {
        $appareils = SigningDevice::query()->where('user_id', $officiel->id)->get();

        if ($appareils->isEmpty()) {
            throw new DomainException(
                "Aucun appareil n'est enrôlé sur votre compte. Utilisez votre code "
                ."d'authentification, ou enrôlez un appareil depuis la page Sécurité."
            );
        }

        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->relyingParty()->id,
            allowCredentials: $appareils
                ->map(fn (SigningDevice $a): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $this->decode($a->credential_id),
                ))
                ->all(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::CHALLENGE_TTL * 1000,
        );
    }

    /**
     * Verifie l'assertion et rend l'appareil qui a signe.
     *
     * @throws DomainException
     */
    public function verify(
        User $officiel,
        string $reponseJson,
        PublicKeyCredentialRequestOptions $options,
        string $host,
    ): SigningDevice {
        $credential = $this->deserialize($reponseJson);
        $reponse = $credential->response;

        if (! $reponse instanceof AuthenticatorAssertionResponse) {
            throw new DomainException("La réponse de l'appareil n'est pas une signature.");
        }

        $appareil = SigningDevice::query()
            ->where('user_id', $officiel->id)
            ->where('credential_id', $this->encode($credential->rawId))
            ->first();

        if ($appareil === null) {
            // Ne PAS dire « cet appareil n'est pas le vôtre » : la reponse ne
            // doit pas apprendre quels appareils existent.
            throw new DomainException(
                "L'appareil n'a pas pu signer. Vérifiez qu'il est bien enrôlé sur ce compte."
            );
        }

        try {
            $record = AuthenticatorAssertionResponseValidator::create(
                $this->ceremonies($host)->requestCeremony()
            )->check(
                $this->toRecord($appareil, $officiel),
                $reponse,
                $options,
                $host,
                $this->userHandle($officiel),
            );
        } catch (Throwable $e) {
            throw new DomainException("La signature de l'appareil a été refusée : ".$e->getMessage());
        }

        $appareil->forceFill([
            'sign_count' => $record->counter,
            'last_used_at' => now(),
        ])->save();

        return $appareil;
    }

    /* ------------------------------------------------------------------ */
    /* Le défi, gardé en session */
    /* ------------------------------------------------------------------ */

    /**
     * Range le defi en session, LIE A CE QU'IL AUTORISE.
     *
     * POURQUOI LE CONTEXTE EST RANGE AVEC LUI. Une assertion valide n'est
     * qu'une preuve que le maire a deverrouille son appareil. Sans lien avec
     * le dossier, une assertion obtenue pour un dossier pourrait etre
     * presentee pour un autre — ou une assertion d'enrolement servir a signer.
     * Le defi est donc range avec l'usage auquel il est destine, et relu avec.
     *
     * En session, et non en base : il ne vaut que pour cette personne, dans
     * cette fenetre, et il disparait avec elle.
     */
    public function rememberChallenge(string $usage, PublicKeyCredentialOptions $options, array $contexte = []): void
    {
        session()->put("phoenix.webauthn.{$usage}", [
            'options' => $this->serializer()->serialize($options, 'json'),
            'contexte' => $contexte,
            'pose_a' => now()->timestamp,
        ]);
    }

    /**
     * Relit le defi et le RETIRE : un defi ne sert qu'une fois.
     *
     * @param  array<string, mixed>  $contexteAttendu
     *
     * @throws DomainException
     */
    public function recallRequestOptions(string $usage, array $contexteAttendu = []): PublicKeyCredentialRequestOptions
    {
        return $this->recall($usage, $contexteAttendu, PublicKeyCredentialRequestOptions::class);
    }

    /**
     * @param  array<string, mixed>  $contexteAttendu
     *
     * @throws DomainException
     */
    public function recallCreationOptions(string $usage, array $contexteAttendu = []): PublicKeyCredentialCreationOptions
    {
        return $this->recall($usage, $contexteAttendu, PublicKeyCredentialCreationOptions::class);
    }

    /**
     * @template T of PublicKeyCredentialOptions
     *
     * @param  array<string, mixed>  $contexteAttendu
     * @param  class-string<T>  $type
     * @return T
     */
    private function recall(string $usage, array $contexteAttendu, string $type): PublicKeyCredentialOptions
    {
        $range = session()->pull("phoenix.webauthn.{$usage}");

        if (! is_array($range) || ! isset($range['options'])) {
            throw new DomainException(
                'La demande a expiré. Recommencez : votre appareil vous sera redemandé.'
            );
        }

        if ((int) ($range['pose_a'] ?? 0) + self::CHALLENGE_TTL < now()->timestamp) {
            throw new DomainException('La demande a expiré. Recommencez.');
        }

        foreach ($contexteAttendu as $cle => $valeur) {
            if (($range['contexte'][$cle] ?? null) !== $valeur) {
                // Le defi ne portait pas sur ce qu'on lui demande d'autoriser.
                throw new DomainException(
                    "Cette demande d'appareil ne correspond pas au dossier en cours. Recommencez."
                );
            }
        }

        /** @var T $options */
        $options = $this->serializer()->deserialize((string) $range['options'], $type, 'json');

        return $options;
    }

    /* ------------------------------------------------------------------ */
    /* Plomberie */
    /* ------------------------------------------------------------------ */

    public function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            (string) config('app.name', 'PHOENIX'),
            (string) (config('phoenix.security.webauthn_rp_id') ?: parse_url((string) config('app.url'), PHP_URL_HOST)),
        );
    }

    public function userEntity(User $officiel): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $officiel->email,
            $this->userHandle($officiel),
            $officiel->name,
        );
    }

    /**
     * L'identifiant opaque de l'utilisateur transmis a l'appareil.
     *
     * Ni le courriel ni l'identifiant en base : c'est une valeur que
     * l'appareil peut conserver et qu'un tiers pourrait lire s'il s'en
     * emparait. Derive de APP_KEY, elle ne dit rien du compte.
     */
    private function userHandle(User $officiel): string
    {
        return hash_hmac('sha256', 'phoenix-webauthn-user:'.$officiel->id, (string) config('app.key'), true);
    }

    private function ceremonies(string $host): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;

        // L'origine attendue est fixee explicitement : accepter n'importe
        // quelle origine reviendrait a accepter une signature obtenue depuis
        // un autre site.
        $factory->setAllowedOrigins([$this->origin($host)]);

        return $factory;
    }

    private function origin(string $host): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base !== '' ? $base : 'https://'.$host;
    }

    private function deserialize(string $json): PublicKeyCredential
    {
        try {
            /** @var PublicKeyCredential $credential */
            $credential = $this->serializer()->deserialize($json, PublicKeyCredential::class, 'json');
        } catch (Throwable $e) {
            throw new DomainException("La réponse de l'appareil est illisible.");
        }

        return $credential;
    }

    private function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([new NoneAttestationStatementSupport])
        ))->create();
    }

    private function toRecord(SigningDevice $appareil, User $officiel): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: $this->decode($appareil->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $appareil->transports ?? [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString($appareil->aaguid ?: '00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: $this->decode($appareil->public_key),
            userHandle: $this->userHandle($officiel),
            counter: (int) $appareil->sign_count,
        );
    }

    /**
     * Les options au format JSON attendu par le navigateur.
     *
     * PAS `json_encode()` : les objets de la bibliotheque n'implementent pas
     * JsonSerializable, et `json_encode` rend une chaine vide. C'est le
     * serialiseur de la bibliotheque qui encode le binaire en base64url,
     * comme la specification l'exige. Verifie, pas suppose.
     */
    public function optionsToJson(PublicKeyCredentialOptions $options): string
    {
        return $this->serializer()->serialize($options, 'json');
    }

    /** Base64 URL, sans remplissage : le format que le navigateur emploie. */
    private function encode(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }

    private function decode(string $valeur): string
    {
        return (string) base64_decode(strtr($valeur, '-_', '+/'), true);
    }

    /** Le nom d'hote de la requete, seul element dont depend la ceremonie. */
    public static function hostOf(Request $request): string
    {
        return $request->getHost();
    }
}
