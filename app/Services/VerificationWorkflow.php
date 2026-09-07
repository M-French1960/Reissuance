<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CivilRegistryProvider;
use App\Contracts\IdentityLookupProvider;
use App\Enums\VerificationResult;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Models\VerificationStep;
use App\Support\ProviderResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vérification en 5 étapes (5.3 du brief).
 *
 * Chaque étape est persistée séparément : une vérification interrompue est
 * reprenable, et on sait qui a fait quoi, quand, avec quel résultat. Le
 * prototype gardait tout en mémoire dans une variable JavaScript — une page
 * rechargée perdait tout (docs/AUDIT_FRONTEND.md 5.5).
 *
 * Les lignes ne sont JAMAIS écrasées : un retour du maire incrémente le cycle
 * et une nouvelle passe crée de nouvelles lignes. On garde ainsi la trace de
 * ce qui avait été vu au premier passage, ce qu'un audit anti-fraude a
 * précisément besoin de reconstituer (docs/STATE_MACHINE.md 3.3).
 */
final class VerificationWorkflow
{
    /** Les cinq écrans du poste de vérification. */
    public const STEPS = [
        1 => 'Informations de la demande',
        2 => "Vérification de la pièce d'identité",
        3 => 'Examen des photographies',
        4 => "Recherche dans le registre d'état civil",
        5 => 'Décision',
    ];

    /**
     * Les étapes qui doivent porter un résultat avant toute acceptation.
     *
     * La cinquième, « Décision », n'en fait PAS partie : elle est la décision
     * elle-même, enregistrée dans request_decisions. L'exiger comme préalable
     * à la décision rendait l'acceptation inatteignable — voir D-027.
     *
     * @var list<int>
     */
    public const VERIFICATION_STEPS = [1, 2, 3, 4];

    public function __construct(
        private readonly IdentityLookupProvider $identity,
        private readonly CivilRegistryProvider $registry,
    ) {}

    /** @return Collection<int, VerificationStep> indexée par numéro d'étape */
    public function steps(ReissuanceRequest $request): Collection
    {
        return $request->verificationSteps()
            ->where('cycle', $request->verification_cycle)
            ->get()
            ->keyBy('step');
    }

    /** Les 4 vérifications ont-elles toutes un résultat ? Condition de T4. */
    public function isComplete(ReissuanceRequest $request): bool
    {
        return $this->missingSteps($request) === [];
    }

    /** @return list<int> vérifications restant à renseigner */
    public function missingSteps(ReissuanceRequest $request): array
    {
        $done = $this->steps($request)
            ->filter(fn (VerificationStep $s): bool => $s->result !== null)
            ->keys()->all();

        return array_values(array_diff(self::VERIFICATION_STEPS, $done));
    }

    /**
     * Enregistre le résultat d'une étape.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        ReissuanceRequest $request,
        int $step,
        User $officer,
        VerificationResult $result,
        array $payload = [],
    ): VerificationStep {
        return DB::transaction(function () use ($request, $step, $officer, $result, $payload): VerificationStep {
            $record = VerificationStep::updateOrCreate(
                [
                    'request_id' => $request->id,
                    'cycle' => $request->verification_cycle,
                    'step' => $step,
                ],
                [
                    'officer_id' => $officer->id,
                    'result' => $result->value,
                    'payload' => $payload,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]
            );

            AuditLog::create([
                'actor_id' => $officer->id,
                'actor_role' => $officer->role->value,
                'action' => "verification.step_{$step}_recorded",
                'auditable_type' => 'verification_step',
                'auditable_id' => $record->id,
                // Le résultat, jamais le contenu : le garde-fou n6 interdit
                // toute donnée personnelle dans les journaux.
                'reason' => $result->label(),
                'ip_address' => request()->ip(),
            ]);

            return $record;
        });
    }

    /** Étape 2 : contrôle de la pièce auprès de la base de la police. */
    public function runIdentityCheck(ReissuanceRequest $request, User $officer): ProviderResponse
    {
        $profile = $request->citizen->profile;

        $response = $this->identity->verify(
            (string) ($profile?->national_id_number ?? ''),
            (string) $profile?->fullName(),
        );

        $this->record(
            $request, 2, $officer,
            $response->outcome->toVerificationResult(),
            $response->toArray(),
        );

        return $response;
    }

    /** Étape 4 : recherche de l'acte d'origine. */
    public function runRegistrySearch(ReissuanceRequest $request, User $officer): ProviderResponse
    {
        $response = $this->registry->search([
            'full_name' => $request->full_name_at_birth,
            'date_of_birth' => $request->date_of_birth?->format('Y-m-d'),
            'place_of_birth' => $request->place_of_birth,
            'registration_year' => $request->registration_year,
            'certificate_number' => $request->original_certificate_number,
        ]);

        $this->record(
            $request, 4, $officer,
            $response->outcome->toVerificationResult(),
            $response->toArray(),
        );

        return $response;
    }

    /**
     * Ouvre un nouveau cycle après un retour du maire (T8, T11).
     *
     * Les étapes du cycle précédent sont conservées intactes.
     */
    public function openNewCycle(ReissuanceRequest $request): void
    {
        $request->forceFill([
            'verification_cycle' => $request->verification_cycle + 1,
        ])->save();
    }
}
