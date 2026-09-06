<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RequestAttachment extends Model
{
    /** @use HasFactory<RequestAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id', 'kind', 'disk', 'path', 'mime_type',
        'size_bytes', 'checksum_sha256', 'captured_at', 'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'purge_after' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Chemin opaque, jamais derive du nom ni du numero de piece du citoyen.
     *
     * Un chemin devinable annulerait la protection : il suffirait de connaitre
     * l'identite d'une personne pour construire l'URL de sa piece d'identite.
     */
    public static function generatePath(string $kind): string
    {
        return sprintf('attachments/%s/%s', $kind, Str::uuid()->toString());
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }
}
