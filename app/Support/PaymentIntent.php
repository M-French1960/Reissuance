<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentOperator;

/**
 * Ce qu'on demande a l'operateur.
 *
 * `idempotencyKey` est portee par l'appelant, pas par l'operateur : c'est ce
 * qui permet de rejouer un appel interrompu sans encaisser deux fois.
 */
final readonly class PaymentIntent
{
    public function __construct(
        public Money $amount,
        public string $idempotencyKey,
        public string $requestReference,
        public ?string $payerReference = null,
        public ?PaymentOperator $operator = null,
    ) {}
}
