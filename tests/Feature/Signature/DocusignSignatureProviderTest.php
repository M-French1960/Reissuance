<?php

declare(strict_types=1);

namespace Tests\Feature\Signature;

use App\Contracts\SignatureProvider;
use App\Integrations\Real\Docusign\DocusignClient;
use App\Integrations\Real\DocusignSignatureProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * L'adaptateur Docusign refuse, et il refuse PROPREMENT.
 *
 * Ce que ces tests protegent : qu'on ne « debloque » pas un jour la signature
 * en faisant rendre un succes a cette classe. Un adaptateur non acheve qui
 * renverrait un document reput signe est exactement le chemin par lequel un
 * acte frauduleux sort du systeme (§13 des garde-fous).
 */
class DocusignSignatureProviderTest extends TestCase
{
    #[Test]
    public function l_adaptateur_refuse_de_signer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas active");

        $this->provider()->sign('%PDF-1.4 projet', ['reference' => 'PHX-2026-0001']);
    }

    #[Test]
    public function le_refus_nomme_les_quatre_obstacles(): void
    {
        try {
            $this->provider()->sign('%PDF-1.4 projet', []);
            $this->fail('La signature aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            // Le message doit renseigner celui qui l'obtient, pas seulement
            // l'arrêter : chacun des quatre obstacles y est nommé.
            $this->assertStringContainsString('asynchrone', $message);
            $this->assertStringContainsString('transaction', $message);
            $this->assertStringContainsString('A1', $message);
            // Sans apostrophe dans le motif : le message en emploie une
            // typographique, et un test ne doit pas tomber sur ce détail.
            $this->assertStringContainsString('lieu de traitement', $message);
            $this->assertStringContainsString('compte signataire', $message);
            // Et il dit quoi faire en attendant.
            $this->assertStringContainsString('PHOENIX_SIGNATURE_PROVIDER=fake', $message);
        }
    }

    #[Test]
    public function le_refus_precede_tout_appel_a_docusign(): void
    {
        // Le point qui compte vraiment : refuser APRES avoir créé l'enveloppe
        // laisserait chez un prestataire étranger un acte d'état civil qu'aucun
        // maire n'a signé, sans trace dans PHOENIX.
        Http::fake();

        try {
            $this->provider()->sign('%PDF-1.4 projet', []);
        } catch (RuntimeException) {
            // attendu
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function l_adaptateur_reel_est_constructible_par_le_conteneur(): void
    {
        // `real` sélectionne cette classe, qui a désormais une dépendance.
        // Un `new` sans conteneur échouerait ici — et l'échec surviendrait au
        // pire moment, à la première signature tentée en production.
        config(['phoenix.providers.signature' => 'real']);

        $adaptateur = $this->app->make(SignatureProvider::class);

        $this->assertInstanceOf(DocusignSignatureProvider::class, $adaptateur);
        $this->assertInstanceOf(DocusignClient::class, $adaptateur->client());
    }

    private function provider(): DocusignSignatureProvider
    {
        return new DocusignSignatureProvider(new DocusignClient);
    }
}
