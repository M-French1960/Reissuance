<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'un encaissement.
 *
 * Volontairement SEPARE du cycle de vie de la demande (D-040). Une demande a
 * un etat ; un paiement en a un autre. Les melanger aurait fige une reponse a
 * une question qui n'en a pas : le paiement precede-t-il l'envoi de la demande
 * ou sa signature ? (question 3 d'INTEGRATIONS 5).
 */
enum PaymentStatus: string
{
    /** Initie cote plateforme, l'operateur n'a rien confirme. */
    case Pending = 'pending';

    /** L'operateur a confirme la prise de l'ordre, les fonds ne sont pas acquis. */
    case Authorised = 'authorised';

    /** Fonds acquis. C'est le seul etat qui vaut paiement. */
    case Settled = 'settled';

    /** Refuse par l'operateur, ou par le payeur. */
    case Failed = 'failed';

    /** Sans reponse dans le delai : ni encaisse, ni refuse. */
    case Expired = 'expired';

    /** Rembourse. Voir la question 4 d'INTEGRATIONS 5, toujours ouverte. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de confirmation',
            self::Authorised => 'Autorisé, fonds non acquis',
            self::Settled => 'Payé',
            self::Failed => 'Refusé',
            self::Expired => 'Expiré sans réponse',
            self::Refunded => 'Remboursé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Settled => 'success',
            self::Authorised => 'progress',
            self::Pending => 'waiting',
            self::Failed, self::Expired => 'danger',
            self::Refunded => 'neutral',
        };
    }

    /** Un etat terminal ne se quitte plus. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Refunded], true);
    }

    /** Seul `settled` vaut paiement. `authorised` ne suffit pas. */
    public function isPaid(): bool
    {
        return $this === self::Settled;
    }
}
