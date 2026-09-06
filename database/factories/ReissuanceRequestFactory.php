<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReissuanceRequest> */
class ReissuanceRequestFactory extends Factory
{
    public function definition(): array
    {
        $center = CivilStatusCenter::factory()->create();

        return [
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => User::factory()->citizen(),
            'civil_status_center_id' => $center->id,
            'commune_id' => $center->commune_id,
            'reason' => 'lost',
            'copies_requested' => 1,
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-01',
            'place_of_birth' => 'Ville de test',
            'consent_given_at' => now(),
        ];
    }

    /** Demande deja envoyee : submitted_at est requis hors brouillon. */
    public function submitted(): static
    {
        return $this->afterCreating(
            fn (ReissuanceRequest $r) => $r->forceFill(['submitted_at' => now()])->save()
        );
    }
}
