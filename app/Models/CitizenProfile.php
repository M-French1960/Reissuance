<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\BlindIndex;
use Database\Factories\CitizenProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitizenProfile extends Model
{
    /** @use HasFactory<CitizenProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'birth_date', 'birth_place',
        'national_id_number', 'phone', 'address', 'completed_at',
    ];

    protected $hidden = ['national_id_number', 'national_id_hash'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Ecrire le numero met a jour l'empreinte dans le meme geste.
     *
     * Les deux ne peuvent pas diverger : sans cela, une mise a jour du numero
     * laisserait une empreinte perimee et la recherche renverrait le mauvais
     * dossier — ou aucun.
     */
    protected function nationalIdNumber(): Attribute
    {
        // Le chiffrement est fait ici explicitement, PAS par un cast
        // « encrypted » : les deux ensemble chiffreraient deux fois.
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : decrypt($value),
            set: function (?string $value): array {
                if ($value === null || trim($value) === '') {
                    return ['national_id_number' => null, 'national_id_hash' => null];
                }

                return [
                    'national_id_number' => encrypt(BlindIndex::normalise($value)),
                    'national_id_hash' => BlindIndex::hash($value),
                ];
            },
        );
    }

    /** Recherche par numero exact. La recherche partielle est impossible. */
    public function scopeWhereNationalId($query, string $number)
    {
        return $query->where('national_id_hash', BlindIndex::hash($number));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
