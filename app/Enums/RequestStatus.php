<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Etats d'une demande de reedition.
 *
 * Reference : docs/STATE_MACHINE.md. Cette enumeration ne fait pas autorite a
 * elle seule : la table allowed_transitions et le declencheur MySQL sont
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
     * Retiree par le demandeur lui-meme.
     *
     * Distincte de `rejected` : un rejet est une decision de l'administration,
     * une annulation est un retrait du demandeur. Confondre les deux rendrait
     * le journal illisible et fausserait toute statistique de refus.
     */
    case Cancelled = 'cancelled';

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
            self::Cancelled => 'Annulée par le demandeur',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Signed, self::Rejected, self::Cancelled], true);
    }

    /**
     * Rang du statut sur la frise de suivi du demandeur.
     *
     * Cette correspondance vivait dans un tableau du controleur, indexe par
     * la VALEUR du statut. Ajouter `cancelled` a l'enumeration a donc casse
     * l'ecran de suivi — « Undefined array key » — sans qu'aucun outil ne
     * previenne.
     *
     * Ici, c'est un `match` sur l'enumeration : un cas oublie leve une
     * UnhandledMatchError a la source, au seul endroit qui definit l'ordre, et
     * un test verifie que chaque cas en a un.
     */
    public function timelineRank(): int
    {
        return match ($this) {
            self::Draft => 0,
            self::Pending => 1,
            self::UnderReview, self::Escalated => 2,
            self::AwaitingSignature => 3,
            // Les trois fins de parcours partagent le dernier rang : le
            // parcours s'arrete la, qu'il aboutisse ou non.
            self::Signed, self::Rejected, self::Cancelled => 4,
        };
    }

    /** Le parcours s'est-il arrete sans acte ? */
    public function isStopped(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled], true);
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
            self::Cancelled => 'neutral',
        };
    }
}
