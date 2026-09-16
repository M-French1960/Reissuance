<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationResult;
use App\Services\VerificationWorkflow;
use Database\Factories\VerificationStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationStep extends Model
{
    /** @use HasFactory<VerificationStepFactory> */
    use HasFactory;

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
        return VerificationWorkflow::stepNames()[$this->step]
            ?? __('verification.step_number', ['number' => $this->step]);
    }
}
