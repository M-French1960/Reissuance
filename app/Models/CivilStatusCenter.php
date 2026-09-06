<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CivilStatusCenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CivilStatusCenter extends Model
{
    /** @use HasFactory<CivilStatusCenterFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'city', 'commune_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ReissuanceRequest::class);
    }
}
