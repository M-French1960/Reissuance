<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use Illuminate\Database\Seeder;

/**
 * Demandes de demonstration couvrant les 7 etats.
 *
 * Les etats non-brouillon sont atteints en passant par le service de
 * transition, donc par le declencheur PostgreSQL : le jeu de demonstration
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
                $transitions->transition($request, $to, $actor, $reason);
            }

            $this->command?->line("  {$request->reference} → {$target->label()}");
        }
    }
}
