<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Database\Seeder;

/**
 * Jeu de mesure : du volume, pour que les chiffres veuillent dire quelque
 * chose.
 *
 * Un ecran mesure sur trois lignes ne mesure rien. Ce seeder produit un ordre
 * de grandeur plausible pour un centre d'etat civil, afin que les temps de
 * reponse et les plans d'execution soient ceux d'une base peuplee.
 *
 * DONNEES ENTIEREMENT SYNTHETIQUES. Aucune donnee reelle de citoyen n'entre
 * dans ce depot, jamais (garde-fou n1). Les noms sont fabriques, les numeros
 * de piece portent le prefixe DEMO.
 *
 * Usage : php artisan db:seed --class=VolumeSeeder
 */
class VolumeSeeder extends Seeder
{
    public function run(int $combien = 500): void
    {
        if (app()->environment('production')) {
            $this->command?->error('VolumeSeeder ne tourne pas en production.');

            return;
        }

        $centre = CivilStatusCenter::query()->where('is_active', true)->firstOrFail();

        $officier = User::query()
            ->where('role', UserRole::Officer->value)
            ->where('civil_status_center_id', $centre->id)
            ->firstOrFail();

        $transitions = app(RequestTransitionService::class);
        $workflow = app(VerificationWorkflow::class);

        $depart = (int) User::query()->count() + 1000;
        $barre = $this->command?->getOutput()->createProgressBar($combien);

        for ($i = 0; $i < $combien; $i++) {
            $citoyen = User::create([
                'name' => "Personne SYNTHETIQUE {$i}",
                'email' => "volume-{$depart}-{$i}@exemple.test",
                'password' => 'mot-de-passe-non-utilisable-'.bin2hex(random_bytes(8)),
                'role' => UserRole::Citizen->value,
                'status' => 'active',
            ]);

            $citoyen->profile()->create([
                'first_name' => 'Personne',
                'last_name' => "SYNTHETIQUE {$i}",
                'birth_date' => '1990-01-15',
                'birth_place' => 'Yaoundé',
                'national_id_number' => 'DEMO-VOL-'.str_pad((string) ($depart + $i), 9, '0', STR_PAD_LEFT),
                'phone' => '+237 6 00 00 00 00',
                'address' => 'Adresse synthétique',
                'completed_at' => now(),
            ]);

            $demande = ReissuanceRequest::withoutGlobalScopes()->create([
                'reference' => ReissuanceRequest::generateReference(),
                'user_id' => $citoyen->id,
                'civil_status_center_id' => $centre->id,
                'commune_id' => $centre->commune_id,
                'reason' => $i % 3 === 0 ? 'damaged' : 'lost',
                'full_name_at_birth' => "Personne SYNTHETIQUE {$i}",
                'date_of_birth' => '1990-01-15',
                'place_of_birth' => 'Yaoundé',
                'registration_year' => 1990,
                'father_name' => 'Père SYNTHÉTIQUE',
                'father_nationality' => 'Camerounaise',
                'mother_name' => 'Mère SYNTHÉTIQUE',
                'mother_nationality' => 'Camerounaise',
                'parents_address' => 'Adresse synthétique',
            ]);
            $demande->forceFill(['submitted_at' => now()->subMinutes($i)])->save();

            $transitions->transition($demande, RequestStatus::Pending, $citoyen);

            // Un tiers reste en attente, un tiers en cours, un tiers pret a
            // signer : les trois files ont du volume.
            if ($i % 3 !== 0) {
                $transitions->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
                $demande->forceFill(['assigned_officer_id' => $officier->id])->save();
            }

            if ($i % 3 === 2) {
                foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
                    $workflow->record($demande->refresh(), $n, $officier, VerificationResult::Match);
                }
                $transitions->transition($demande->refresh(), RequestStatus::AwaitingSignature, $officier);
            }

            $barre?->advance();
        }

        $barre?->finish();
        $this->command?->newLine();
        $this->command?->info("{$combien} demandes synthétiques créées.");
    }
}
