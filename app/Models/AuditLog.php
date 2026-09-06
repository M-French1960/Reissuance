<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Journal en ajout seul.
 *
 * Les garde-fous ci-dessous sont une commodite de developpement, pas la
 * barriere : celle-ci est la revocation des droits UPDATE et DELETE sur le
 * role applicatif PostgreSQL (migration 2026_01_01_000800). Une exception PHP
 * se contourne, une revocation de droit non.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_role', 'action', 'auditable_type', 'auditable_id',
        'from_status', 'to_status', 'reason', 'ip_address', 'session_fingerprint',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException("Le journal d'audit est en ajout seul : modification interdite.");
        });

        static::deleting(function (): never {
            throw new RuntimeException("Le journal d'audit est en ajout seul : suppression interdite.");
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
