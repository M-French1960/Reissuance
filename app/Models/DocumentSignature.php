<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends Model
{
    /** @use HasFactory<DocumentSignatureFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id', 'mayor_id', 'draft_id', 'document_hash',
        'document_path', 'proof_path', 'legally_binding',
        'provider', 'confirmation_method', 'signature_payload', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_payload' => 'array',
            'legally_binding' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function mayor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mayor_id');
    }
}
