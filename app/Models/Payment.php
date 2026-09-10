<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'request_id', 'initiated_by',
        'amount_minor', 'currency', 'minor_unit',
        'provider', 'provider_reference', 'idempotency_key',
        'payer_reference', 'provider_payload',
    ];

    /**
     * Un encaissement nait « en attente ».
     *
     * La valeur par defaut est aussi posee en base ; on la reprend ici pour
     * que l'instance en memoire soit coherente des sa creation. Sans cela,
     * $paiement->status vaut null juste apres create(), et tout ce qui lit
     * l'etat dans la foulee travaille sur du vide.
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'minor_unit' => 'integer',
            'provider_payload' => 'array',
            'authorised_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** Le montant, reconstitue tel qu'il a ete encaisse — pas tel qu'il est configure aujourd'hui. */
    public function money(): Money
    {
        return new Money($this->amount_minor, $this->currency, $this->minor_unit);
    }

    public function isPaid(): bool
    {
        return $this->status->isPaid();
    }
}
