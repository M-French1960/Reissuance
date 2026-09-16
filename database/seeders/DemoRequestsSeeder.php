<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Models\User;
use App\Services\ActDraftService;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Database\Seeder;

/**
 * Demandes de demonstration couvrant les 7 etats.
 *
 * Les etats non-brouillon sont atteints en passant par le service de
 * transition, donc par le declencheur MySQL : le jeu de demonstration
 * ne peut pas contenir une demande dans un etat qu'aucun chemin legitime ne
 * permettrait d'atteindre.
 *
 * Aucune donnee reelle (garde-fou n1, D-004).
 */
class DemoRequestsSeeder extends Seeder
{
    public function run(RequestTransitionService $transitions): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Jeu de démonstration interdit en production.');
        }

        $citizen = User::query()->where('email', 'citoyen@phoenix.test')->firstOrFail();
        $officer = User::query()->where('email', 'officier@phoenix.test')->firstOrFail();
        $mayor = User::query()->where('email', 'maire@phoenix.test')->firstOrFail();
        $center = CivilStatusCenter::query()->where('code', 'YDE-I-CEC')->firstOrFail();

        // Chaque cible decrit le chemin legitime pour l'atteindre.
        $paths = [
            [RequestStatus::Draft, []],
            [RequestStatus::Pending, [[RequestStatus::Pending, $citizen, null]]],
            [RequestStatus::UnderReview, [
                [RequestStatus::Pending, $citizen, null],
                [RequestStatus::UnderReview, $officer, null],
            ]],
            [RequestStatus::AwaitingSignature, [
                [RequestStatus::Pending, $citizen, null],
                [RequestStatus::UnderReview, $officer, null],
                [RequestStatus::AwaitingSignature, $officer, null],
            ]],
            [RequestStatus::Escalated, [
                [RequestStatus::Pending, $citizen, null],
                [RequestStatus::UnderReview, $officer, null],
                [RequestStatus::Escalated, $officer, "Doute sur l'authenticité de la pièce présentée."],
            ]],
            [RequestStatus::Signed, [
                [RequestStatus::Pending, $citizen, null],
                [RequestStatus::UnderReview, $officer, null],
                [RequestStatus::AwaitingSignature, $officer, null],
                [RequestStatus::Signed, $mayor, null],
            ]],
            [RequestStatus::Rejected, [
                [RequestStatus::Pending, $citizen, null],
                [RequestStatus::UnderReview, $officer, null],
                [RequestStatus::Rejected, $officer, 'Les photographies ne correspondent pas à la pièce fournie.'],
            ]],
        ];

        foreach ($paths as [$target, $steps]) {
            $request = ReissuanceRequest::create([
                'reference' => ReissuanceRequest::generateReference(),
                'user_id' => $citizen->id,
                'civil_status_center_id' => $center->id,
                'commune_id' => $center->commune_id,
                'reason' => 'lost',
                'copies_requested' => 1,
                'full_name_at_birth' => 'Citoyen DEMO',
                'date_of_birth' => '1990-01-15',
                'place_of_birth' => 'Yaoundé',
                'registration_year' => 1990,
                'father_name' => 'Père DEMO',
                'father_nationality' => 'Camerounaise',
                'mother_name' => 'Mère DEMO',
                'mother_nationality' => 'Camerounaise',
                'parents_address' => 'Adresse de démonstration',
                'consent_given_at' => now(),
            ]);

            if ($steps !== []) {
                // submitted_at est requis des que l'etat n'est plus draft.
                $request->forceFill(['submitted_at' => now()])->save();
            }

            foreach ($steps as [$to, $actor, $reason]) {
                // Le maire ne peut signer qu'un dossier dont les 5 etapes ont
                // un resultat (4.3 du brief) : le jeu de demonstration doit
                // donc les renseigner, comme le ferait un vrai officier.
                if ($to === RequestStatus::AwaitingSignature) {
                    $workflow = app(VerificationWorkflow::class);
                    foreach ([1, 2, 3, 4, 5] as $etape) {
                        $workflow->record($request, $etape, $officer, VerificationResult::Match);
                    }
                    $request->refresh();
                }

                $depuis = $request->status;

                $transitions->transition($request, $to, $actor, $reason);

                /*
                 * LA DECISION DE L'OFFICIER, ET PAS SEULEMENT SON EFFET
                 * (D-074).
                 *
                 * Le jeu de demonstration ne posait que la transition. Il
                 * produisait donc un dossier escalade SANS decision — un etat
                 * que l'application ne sait pas creer : le controleur exige un
                 * motif, et la contrainte
                 * request_decisions_reason_required_check l'impose en base.
                 *
                 * Le maire voyait « Motif de l'escalade : — » sur sa file, et
                 * un historique vide sur l'ecran d'arbitrage : la demonstration
                 * lui cachait exactement ce qu'il doit lire avant de trancher.
                 */
                $decision = match ($to) {
                    RequestStatus::AwaitingSignature => DecisionType::Accepted,
                    RequestStatus::Rejected => DecisionType::Rejected,
                    RequestStatus::Escalated => DecisionType::Escalated,
                    default => null,
                };

                if ($decision !== null) {
                    RequestDecision::create([
                        'request_id' => $request->id,
                        'actor_id' => $actor->id,
                        'actor_role' => $actor->role->value,
                        'decision' => $decision->value,
                        'reason' => $reason,
                        'from_status' => $depuis->value,
                        'to_status' => $to->value,
                    ]);
                }

                /*
                 * Le maire signe un PROJET etabli par l'officier (D-064). Sans
                 * projet, aucun dossier du jeu de demonstration ne serait
                 * signable — c'est le meme trou qui a fait tomber douze tests
                 * le jour ou la regle est entree en vigueur.
                 */
                if (in_array($to, [RequestStatus::AwaitingSignature, RequestStatus::Escalated], true)) {
                    app(ActDraftService::class)->draft($request->refresh(), $officer);
                }
            }

            $this->command?->line("  {$request->reference} → {$target->label()}");
        }
    }
}
