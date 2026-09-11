<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projet d'acte redige par l'officier.
 *
 * Un projet N'EST PAS un acte : il n'a ni signataire, ni preuve, ni valeur, et
 * le citoyen n'y accede jamais. Il porte le contenu que l'officier propose au
 * maire, et l'empreinte de ce contenu — c'est elle qui garantit que le maire
 * signe ce qu'il a lu (D-064).
 */
class ActDraft extends Model
{
    protected $fillable = [
        'request_id',
        'officer_id',
        'content_hash',
        'document_path',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
