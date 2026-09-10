<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestMessage extends Model
{
    protected $fillable = ['request_id', 'author_id', 'author_role', 'body'];

    protected function casts(): array
    {
        return [
            'author_role' => UserRole::class,
            'read_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isFromCitizen(): bool
    {
        return $this->author_role === UserRole::Citizen;
    }
}
