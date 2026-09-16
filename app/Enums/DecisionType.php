<?php

declare(strict_types=1);

namespace App\Enums;

enum DecisionType: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Escalated = 'escalated';
    case Signed = 'signed';
    case ApprovedByException = 'approved_by_exception';
    case Returned = 'returned';

    /** Motif obligatoire pour tout ce qui n'est pas une acceptation simple. */
    public function requiresReason(): bool
    {
        return ! in_array($this, [self::Accepted, self::Signed], true);
    }

    public function label(): string
    {
        return __('enums.decision.'.$this->value);
    }
}
