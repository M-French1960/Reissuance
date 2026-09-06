<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationResult;
use Database\Factories\VerificationStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationStep extends Model
{
    /** @use HasFactory<VerificationStepFactory> */
    use HasFactory;

    public const STEPS = [
        1 => 'Consultation des informations de la demande',
        2 => "Vérification de la pièce d'identité",
        3 => 'Examen des photographies',
        4 => "Recherche dans la base d'état civil",
        5 => 'Décision',
    ];

    protected $fillable = [
        'request_id', 'cycle', 'step', 'officer_id', 'result',
        'payload', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => VerificationResult::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReissuanceRequest::class, 'request_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function label(): string
    {
        return self::STEPS[$this->step] ?? "Étape {$this->step}";
    }
}
