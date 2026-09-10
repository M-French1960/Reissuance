<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CivilRegistryProvider;
use App\Contracts\IdentityLookupProvider;
use App\Contracts\PaymentProvider;
use App\Contracts\SignatureProvider;
use App\Integrations\Fake\FakeCivilRegistryProvider;
use App\Integrations\Fake\FakeIdentityLookupProvider;
use App\Integrations\Fake\FakePaymentProvider;
use App\Integrations\Fake\FakeSignatureProvider;
use App\Integrations\Real\AccreditedSignatureProvider;
use App\Integrations\Real\MobileMoneyPaymentProvider;
use App\Integrations\Real\NationalCivilRegistryProvider;
use App\Integrations\Real\PoliceIdentityLookupProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Selection des adaptateurs par variable d'environnement (9 du brief).
 * Defaut : les adaptateurs factices — aucune API reelle n'est documentee.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindProvider(
            IdentityLookupProvider::class,
            'identity',
            FakeIdentityLookupProvider::class,
            PoliceIdentityLookupProvider::class,
        );

        $this->bindProvider(
            CivilRegistryProvider::class,
            'registry',
            FakeCivilRegistryProvider::class,
            NationalCivilRegistryProvider::class,
        );

        $this->bindProvider(
            SignatureProvider::class,
            'signature',
            FakeSignatureProvider::class,
            AccreditedSignatureProvider::class,
        );

        $this->bindProvider(
            PaymentProvider::class,
            'payment',
            FakePaymentProvider::class,
            MobileMoneyPaymentProvider::class,
        );
    }

    /**
     * @param  class-string  $contract
     * @param  class-string  $fake
     * @param  class-string  $real
     */
    private function bindProvider(string $contract, string $key, string $fake, string $real): void
    {
        $this->app->bind($contract, function () use ($key, $fake, $real): object {
            $choice = (string) config("phoenix.providers.{$key}");

            return match ($choice) {
                'fake' => new $fake,
                'real' => new $real,
                // Une valeur inconnue echoue bruyamment plutot que de retomber
                // silencieusement sur un adaptateur qu'on n'a pas choisi.
                default => throw new InvalidArgumentException(
                    "Adaptateur « {$choice} » inconnu pour {$key}. Valeurs acceptées : fake, real."
                ),
            };
        });
    }
}
