<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentStatus;

/**
 * Ce que l'operateur repond.
 *
 * `status` est l'etat que l'operateur declare. La plateforme ne le recopie
 * jamais aveuglement : PaymentService verifie que la transition est autorisee
 * avant de l'appliquer, et la base la refuse si elle ne l'est pas.
 */
final readonly class PaymentOutcome
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public PaymentStatus $status,
        public string $provider,
        public ?string $providerReference = null,
        public ?string $message = null,
        public array $payload = [],
        public ?Money $amount = null,
    ) {}

    /** L'operateur n'a pas repondu : ni encaisse, ni refuse. */
    public function isUnavailable(): bool
    {
        return $this->status === PaymentStatus::Expired
            && ($this->payload['unavailable'] ?? false) === true;
    }
}
