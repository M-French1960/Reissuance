<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReissuanceRequest;
use App\Support\Money;
use InvalidArgumentException;

/**
 * OU le paiement est exige.
 *
 * C'est la question 3 d'INTEGRATIONS 5, sans reponse : le paiement
 * precede-t-il l'envoi de la demande, ou sa signature ? C'est une decision de
 * service, pas d'ingenierie. Elle est donc un REGLAGE, et les deux placements
 * sont construits (D-041).
 *
 * Le defaut est `none` : tant que la question n'est pas tranchee, la
 * plateforme n'encaisse rien du tout.
 */
final class PaymentGate
{
    public const NONE = 'none';

    public const BEFORE_SUBMISSION = 'before_submission';

    public const BEFORE_SIGNATURE = 'before_signature';

    public const PLACEMENTS = [self::NONE, self::BEFORE_SUBMISSION, self::BEFORE_SIGNATURE];

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Le placement configure.
     *
     * Une valeur inconnue echoue bruyamment. Retomber silencieusement sur
     * « aucun paiement » ferait d'une faute de frappe une gratuite generale ;
     * retomber sur « paiement exige » bloquerait le service. Ni l'un ni
     * l'autre ne doit arriver sans qu'on le sache.
     */
    public function placement(): string
    {
        $valeur = (string) config('phoenix.payments.gate', self::NONE);

        if (! in_array($valeur, self::PLACEMENTS, true)) {
            throw new InvalidArgumentException(
                "Placement de paiement « {$valeur} » inconnu. Valeurs acceptées : "
                .implode(', ', self::PLACEMENTS).'.'
            );
        }

        return $valeur;
    }

    public function isEnabled(): bool
    {
        return $this->placement() !== self::NONE;
    }

    /** Le paiement est-il exige AVANT l'envoi de la demande par le citoyen ? */
    public function requiredBeforeSubmission(): bool
    {
        return $this->placement() === self::BEFORE_SUBMISSION;
    }

    /** Le paiement est-il exige AVANT la signature du maire ? */
    public function requiredBeforeSignature(): bool
    {
        return $this->placement() === self::BEFORE_SIGNATURE;
    }

    /**
     * La demande peut-elle franchir ce point ?
     *
     * Rend `true` quand le paiement n'est pas exige a cet endroit : le
     * placement decide, l'appelant n'a pas a le savoir.
     */
    public function allows(ReissuanceRequest $request, string $moment): bool
    {
        if ($this->placement() !== $moment) {
            return true;
        }

        return $this->payments->isPaid($request);
    }

    /**
     * Le montant exige, ou null si rien n'est exige.
     *
     * Leve si l'encaissement est active sans tarif : la plateforme refuse de
     * servir plutot que de facturer un chiffre invente (D-039).
     */
    public function amount(): ?Money
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return Money::fromConfig();
    }

    /**
     * La base reglementaire du tarif, si elle est connue.
     *
     * Vide tant que la question 1 d'INTEGRATIONS 5 est sans reponse : on
     * n'affiche pas un fondement juridique qu'on ne peut pas citer (10 du
     * brief).
     */
    public function legalBasis(): ?string
    {
        $base = trim((string) config('phoenix.payments.legal_basis', ''));

        return $base === '' ? null : $base;
    }
}
