<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Etats d'une demande de reedition.
 *
 * Reference : docs/STATE_MACHINE.md. Cette enumeration ne fait pas autorite a
 * elle seule : la table allowed_transitions et le declencheur PostgreSQL sont
 * la barriere reelle. Les deux sont tenus synchronises par un test.
 */
enum RequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case AwaitingSignature = 'awaiting_signature';
    case Escalated = 'escalated';
    case Signed = 'signed';
    case Rejected = 'rejected';

    /**
     * Libelle affiche a l'utilisateur.
     *
     * Le 8.1 du brief interdit d'afficher la valeur technique. Le prototype
     * affichait « escalated » brut ; ce defaut n'est pas porte.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon — non envoyée',
            self::Pending => 'Envoyée — en attente de traitement',
            self::UnderReview => 'En cours de vérification',
            self::AwaitingSignature => 'En attente de signature du maire',
            self::Escalated => 'Transmise au maire pour arbitrage',
            self::Signed => 'Signée — acte disponible',
            self::Rejected => 'Refusée',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Signed, self::Rejected], true);
    }

    /** Jeton de couleur du design system, jamais une couleur en dur. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Pending => 'waiting',
            self::UnderReview => 'progress',
            self::AwaitingSignature => 'progress',
            self::Escalated => 'attention',
            self::Signed => 'success',
            self::Rejected => 'danger',
        };
    }
}
