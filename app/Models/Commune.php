<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommuneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    /** @use HasFactory<CommuneFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'region', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function centers(): HasMany
    {
        return $this->hasMany(CivilStatusCenter::class);
    }

    public function mayors(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
