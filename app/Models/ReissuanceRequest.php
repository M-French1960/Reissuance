<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Scopes\RequestVisibilityScope;
use Database\Factories\ReissuanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/*
 * Portee globale : nom pleinement qualifie a dessein.
 *
 * Un `use` a ete supprime ici par un formateur qui l'a juge inutilise, et
 * l'attribut a alors pointe vers une classe inexistante — la portee a disparu
 * SANS AUCUNE ERREUR. Le test R13 l'a rattrape ; le nom complet empeche que
 * cela se reproduise.
 */
#[ScopedBy(RequestVisibilityScope::class)]
class ReissuanceRequest extends Model
{
    /** @use HasFactory<ReissuanceRequestFactory> */
    use HasFactory;

    /**
     * `status` est volontairement absent : il n'est jamais assignable en masse.
     * Seul le service de transition l'ecrit, et le declencheur PostgreSQL
     * refuse toute transition non autorisee (docs/STATE_MACHINE.md 4).
     */
    protected $fillable = [
        'reference', 'user_id', 'civil_status_center_id', 'commune_id',
        'document_type', 'reason', 'copies_requested',
        'full_name_at_birth', 'date_of_birth', 'place_of_birth',
        'registration_year', 'original_certificate_number',
        'father_name', 'father_nationality', 'mother_name', 'mother_nationality',
        'parents_address', 'consent_given_at', 'supersedes_id',
        'last_completed_step',
    ];

    /**
     * Une demande nait toujours en brouillon. La valeur par defaut est aussi
     * posee en base ; on la reprend ici pour que le modele soit coherent
     * avant meme d'etre relu depuis la base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'date_of_birth' => 'date',
            'consent_given_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Reference publique non sequentielle.
     *
     * Un compteur laisserait deduire le volume traite et enumerer les
     * demandes d'autrui en changeant un chiffre dans l'URL.
     */
    public static function generateReference(): string
    {
        return 'PHX-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CivilStatusCenter::class, 'civil_status_center_id');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function verificationSteps(): HasMany
    {
        return $this->hasMany(VerificationStep::class, 'request_id');
    }

    /** Avis de comparaison faciale rendus sur ce dossier (etape 3). */
    public function facialComparisons(): HasMany
    {
        return $this->hasMany(FacialComparison::class, 'request_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(RequestDecision::class, 'request_id');
    }

    public function signature(): HasOne
    {
        return $this->hasOne(DocumentSignature::class, 'request_id');
    }

    /** Une demande envoyee n'est jamais visible en tant que brouillon. */
    public function scopeSubmitted($query)
    {
        return $query->where('status', '!=', RequestStatus::Draft->value);
    }

    public function isDraft(): bool
    {
        return $this->status === RequestStatus::Draft;
    }
}
