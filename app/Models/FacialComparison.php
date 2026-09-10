<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ProviderOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis rendu par la comparaison faciale.
 *
 * Ne contient ni image ni gabarit biometrique : une issue et un score.
 */
class FacialComparison extends Model
{
    protected $fillable = ['request_id', 'cycle', 'outcome', 'payload'];

    protected function casts(): array
    {
        return [
            'outcome' => ProviderOutcome::class,
            'payload' => 'array',
            'cycle' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    /** Le score de similarite, s'il a ete rendu. */
    public function similarity(): ?float
    {
        $valeur = $this->payload['payload']['similarity'] ?? null;

        return is_numeric($valeur) ? (float) $valeur : null;
    }
}
