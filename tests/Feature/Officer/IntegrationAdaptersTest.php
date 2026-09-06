<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Contracts\CivilRegistryProvider;
use App\Contracts\IdentityLookupProvider;
use App\Enums\VerificationResult;
use App\Integrations\Fake\FakeCivilRegistryProvider;
use App\Integrations\Fake\FakeIdentityLookupProvider;
use App\Integrations\Real\NationalCivilRegistryProvider;
use App\Integrations\Real\PoliceIdentityLookupProvider;
use App\Support\ProviderOutcome;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Les quatre exigences du 9 du brief sur les adaptateurs.
 */
class IntegrationAdaptersTest extends TestCase
{
    #[Test]
    public function les_adaptateurs_factices_sont_le_defaut(): void
    {
        $this->assertInstanceOf(FakeIdentityLookupProvider::class, app(IdentityLookupProvider::class));
        $this->assertInstanceOf(FakeCivilRegistryProvider::class, app(CivilRegistryProvider::class));
    }

    #[Test]
    public function la_selection_par_variable_d_environnement_fonctionne(): void
    {
        config(['phoenix.providers.identity' => 'real']);
        $this->assertInstanceOf(PoliceIdentityLookupProvider::class, app(IdentityLookupProvider::class));

        config(['phoenix.providers.registry' => 'real']);
        $this->assertInstanceOf(NationalCivilRegistryProvider::class, app(CivilRegistryProvider::class));
    }

    /** Une valeur inconnue échoue bruyamment, sans repli silencieux. */
    #[Test]
    public function un_adaptateur_inconnu_leve_une_exception(): void
    {
        config(['phoenix.providers.identity' => 'inexistant']);

        $this->expectException(InvalidArgumentException::class);
        app(IdentityLookupProvider::class);
    }

    /**
     * Un squelette non implémenté qui renverrait « correspondance trouvée »
     * serait exactement le chemin par lequel un acte frauduleux sort du
     * système. Il doit donc lever, jamais retourner.
     */
    #[Test]
    public function le_squelette_reel_de_la_police_leve_une_exception_explicite(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/n'est pas implémenté/");

        (new PoliceIdentityLookupProvider)->verify('DEMO-1', 'Personne DE TEST');
    }

    #[Test]
    public function le_squelette_reel_du_registre_leve_une_exception_explicite(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/n'est pas implémenté/");

        (new NationalCivilRegistryProvider)->search(['full_name' => 'Personne DE TEST']);
    }

    /** Jeux de test de l'adaptateur police (docs/INTEGRATIONS.md 2). */
    #[Test]
    #[DataProvider('casPolice')]
    public function l_adaptateur_police_couvre_les_cas_degrades(string $numero, ProviderOutcome $attendu): void
    {
        $reponse = (new FakeIdentityLookupProvider)->verify($numero, 'Personne DE TEST');

        $this->assertSame($attendu, $reponse->outcome);
        $this->assertSame(FakeIdentityLookupProvider::PROVIDER, $reponse->provider);
        $this->assertNotNull($reponse->correlationId);
    }

    /** @return iterable<string, array{string, ProviderOutcome}> */
    public static function casPolice(): iterable
    {
        yield 'correspondance' => ['DEMO-100000001', ProviderOutcome::Match];
        yield 'nom different' => ['DEMO-NOMATCH-1', ProviderOutcome::NoMatch];
        yield 'piece volee' => ['DEMO-STOLEN-1', ProviderOutcome::NoMatch];
        yield 'nom approchant' => ['DEMO-DOUBT-1', ProviderOutcome::Inconclusive];
        yield 'service en panne' => ['DEMO-DOWN-1', ProviderOutcome::Unavailable];
        yield 'delai depasse' => ['DEMO-TIMEOUT-1', ProviderOutcome::Unavailable];
    }

    /**
     * Le déclenchement doit survivre à la ponctuation : le numéro est stocké
     * normalisé, et l'adaptateur applique la même normalisation.
     */
    #[Test]
    public function les_declencheurs_resistent_a_la_ponctuation(): void
    {
        $adaptateur = new FakeIdentityLookupProvider;

        foreach (['DEMO-DOWN-1', 'demo down 1', 'DEMO.DOWN.1', 'DemoDown1'] as $variante) {
            $this->assertSame(
                ProviderOutcome::Unavailable,
                $adaptateur->verify($variante, 'X')->outcome,
                "La variante « {$variante} » doit déclencher le même cas."
            );
        }
    }

    /** @return iterable<string, array{string, ProviderOutcome, int}> */
    public static function casRegistre(): iterable
    {
        yield 'acte unique' => ['Personne DE TEST', ProviderOutcome::Match, 1];
        yield 'aucun acte' => ['Personne INTROUVABLE', ProviderOutcome::NoMatch, 0];
        yield 'homonymes' => ['Personne HOMONYME', ProviderOutcome::Inconclusive, 2];
        yield 'registre detruit' => ['Personne DETRUIT', ProviderOutcome::Inconclusive, 1];
        yield 'registre injoignable' => ['Personne PANNE', ProviderOutcome::Unavailable, 0];
    }

    #[Test]
    #[DataProvider('casRegistre')]
    public function l_adaptateur_registre_couvre_les_cas_degrades(string $nom, ProviderOutcome $attendu, int $nbActes): void
    {
        $reponse = (new FakeCivilRegistryProvider)->search([
            'full_name' => $nom, 'registration_year' => 1990,
        ]);

        $this->assertSame($attendu, $reponse->outcome);
        $this->assertCount($nbActes, $reponse->payload['records'] ?? []);
    }

    /** Le garde-fou n6 : aucune donnée personnelle dans ce qu'on journalise. */
    #[Test]
    public function la_reponse_expose_un_identifiant_de_correlation(): void
    {
        $reponse = (new FakeIdentityLookupProvider)->verify('DEMO-100000001', 'Personne DE TEST');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $reponse->correlationId);
        $this->assertStringNotContainsString('100000001', (string) $reponse->correlationId);
    }

    #[Test]
    public function une_indisponibilite_est_un_resultat_pas_une_exception(): void
    {
        $reponse = (new FakeIdentityLookupProvider)->verify('DEMO-DOWN-1', 'X');

        $this->assertFalse($reponse->isUsable());
        $this->assertSame(
            VerificationResult::ProviderUnavailable,
            $reponse->outcome->toVerificationResult()
        );
        $this->assertNotNull($reponse->message, "Une panne doit s'expliquer, pas rester muette.");
    }
}
