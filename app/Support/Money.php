<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Un montant, en unite mineure entiere.
 *
 * JAMAIS de flottant pour de l'argent : 0.1 + 0.2 ne vaut pas 0.3 en binaire,
 * et un service public qui encaisse ne peut pas se permettre un centime de
 * derive par arrondi. Le montant est un entier d'unites mineures, la devise
 * est explicite, et la conversion en texte est la seule operation qui produit
 * une virgule.
 *
 * Le franc CFA n'a pas de subdivision en usage : `minorUnit` vaut 0 et un
 * montant de 1 000 F s'ecrit 1000.
 */
final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public string $currency,
        public int $minorUnit = 0,
    ) {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException('Un montant negatif ne represente pas un encaissement.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Devise invalide : {$currency}. Un code ISO 4217 est attendu.");
        }

        if ($minorUnit < 0 || $minorUnit > 4) {
            throw new InvalidArgumentException("Nombre de decimales invalide : {$minorUnit}.");
        }
    }

    /**
     * Construit le montant a partir de la configuration.
     *
     * REFUSE de servir un montant absent plutot que d'en supposer un : un
     * tarif de service public se lit dans un texte (D-039).
     */
    public static function fromConfig(): self
    {
        $montant = config('phoenix.payments.amount_minor');

        if ($montant === null || $montant === '') {
            throw new InvalidArgumentException(
                "Aucun tarif n'est configuré (PHOENIX_PAYMENT_AMOUNT_MINOR). "
                ."L'encaissement ne peut pas être activé sans un montant issu d'un texte réglementaire. "
                .'Voir docs/INTEGRATIONS.md §5, question 1.'
            );
        }

        if (! is_numeric($montant) || (string) (int) $montant !== (string) $montant) {
            throw new InvalidArgumentException(
                "Le tarif configuré ({$montant}) n'est pas un entier d'unités mineures. "
                .'Un montant en virgule flottante est refusé : voir App\Support\Money.'
            );
        }

        return new self(
            (int) $montant,
            (string) config('phoenix.payments.currency'),
            (int) config('phoenix.payments.minor_unit'),
        );
    }

    public function equals(self $autre): bool
    {
        return $this->minorAmount === $autre->minorAmount
            && $this->currency === $autre->currency
            && $this->minorUnit === $autre->minorUnit;
    }

    /** Rendu lisible. La seule operation qui produit une virgule. */
    public function format(): string
    {
        if ($this->minorUnit === 0) {
            return number_format($this->minorAmount, 0, ',', ' ').' '.$this->currency;
        }

        $diviseur = 10 ** $this->minorUnit;

        return number_format(
            $this->minorAmount / $diviseur,
            $this->minorUnit,
            ',',
            ' ',
        ).' '.$this->currency;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
