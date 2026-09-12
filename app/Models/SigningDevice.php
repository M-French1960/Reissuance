<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appareil enrole par un officiel pour signer — WebAuthn (D-070).
 *
 * CE QUE CE MODELE NE PORTE PAS : aucune donnee biometrique. Le visage ou le
 * doigt deverrouille une cle PRIVEE qui ne quitte jamais l'appareil ; le
 * serveur ne connait que la cle publique correspondante. Rien n'est envoye,
 * rien n'est conserve — c'est la difference de fond avec la comparaison
 * faciale du demandeur (docs/BIOMETRIE.md).
 */
class SigningDevice extends Model
{
    protected $fillable = [
        'user_id', 'credential_id', 'public_key', 'label',
        'sign_count', 'aaguid', 'transports',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'sign_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
