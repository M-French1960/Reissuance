<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Reponse d'un adaptateur externe.
 *
 * `correlationId` sert a rapprocher notre trace de celle du tiers en cas de
 * litige. C'est LUI qu'on journalise, jamais le numero de piece : le
 * garde-fou n6 interdit toute donnee personnelle dans les journaux
 * applicatifs.
 */
final readonly class ProviderResponse
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public ProviderOutcome $outcome,
        public array $payload = [],
        public string $provider = 'unknown',
        public ?string $correlationId = null,
        public ?string $message = null,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function make(
        ProviderOutcome $outcome,
        string $provider,
        array $payload = [],
        ?string $message = null,
    ): self {
        return new self(
            outcome: $outcome,
            payload: $payload,
            provider: $provider,
            correlationId: (string) Str::uuid(),
            message: $message,
        );
    }

    public function isUsable(): bool
    {
        return $this->outcome !== ProviderOutcome::Unavailable;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'provider' => $this->provider,
            'correlation_id' => $this->correlationId,
            'message' => $this->message,
            'payload' => $this->payload,
            'queried_at' => now()->toIso8601String(),
        ];
    }
}
