<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DecisionType;
use App\Enums\UserRole;
use Database\Factories\RequestDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDecision extends Model
{
    /** @use HasFactory<RequestDecisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'request_id', 'actor_id', 'actor_role', 'decision',
        'reason', 'internal_notes', 'from_status', 'to_status',
    ];

    protected function casts(): array
    {
        return [
            'decision' => DecisionType::class,
            'actor_role' => UserRole::class,
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
