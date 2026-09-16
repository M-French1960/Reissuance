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

    /**
     * Le centre, situé — sans répéter ce que son nom dit déjà (D-075).
     *
     * Les centres s'appellent « Centre d'état civil de Yaoundé I ». Coller la
     * commune à côté donnait, sur l'écran de suivi du citoyen :
     *
     *     Centre d'état civil : Centre d'état civil de Yaoundé I
     *                           — commune de Yaoundé I
     *
     * La commune reste pourtant utile : une commune peut avoir plusieurs
     * centres, et « Centre annexe de Tsinga » ne dit pas où il se trouve. On
     * ne l'ajoute donc que lorsque le nom ne la porte pas déjà.
     *
     * La comparaison ignore casse et accents : « Yaoundé » et « YAOUNDE »
     * désignent la même commune.
     */
    public function situation(): string
    {
        $commune = $this->commune?->name;

        if ($commune === null || $this->nomContient($commune)) {
            return $this->name;
        }

        return "{$this->name} — commune de {$commune}";
    }

    private function nomContient(string $commune): bool
    {
        $pliage = static fn (string $v): string => mb_strtolower(
            (string) iconv('UTF-8', 'ASCII//TRANSLIT', $v)
        );

        return str_contains($pliage($this->name), $pliage($commune));
    }
}
