<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\CitizenProfile;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demandes exerçant les cas dégradés des adaptateurs externes.
 *
 * Le 5 du brief demande que les écrans soient testables dans leurs états
 * dégradés, pas seulement dans le cas heureux. Un officier doit pouvoir voir à
 * quoi ressemble une base injoignable, un homonyme, un acte détruit — avant
 * que cela n'arrive en production.
 *
 * Aucune donnée réelle (garde-fou n1) : les numéros sont des déclencheurs
 * documentés dans docs/INTEGRATIONS.md, hors de tout format réel.
 */
class DemoProviderCasesSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private const CASES = [
        ['DEMO-NOMATCH-001', 'Personne NON-CORRESPONDANTE', 'Pièce connue, mais nom différent'],
        ['DEMO-STOLEN-001', 'Personne PIECE-VOLEE', 'Pièce signalée volée'],
        ['DEMO-DOUBT-001', 'Personne NOM-APPROCHANT', 'Nom proche sans être identique'],
        ['DEMO-DOWN-001', 'Personne SERVICE-EN-PANNE', 'Base de la police injoignable'],
        ['DEMO-REG-001', 'Personne HOMONYME', 'Plusieurs actes correspondent'],
        ['DEMO-REG-002', 'Personne INTROUVABLE', 'Aucun acte au registre'],
        ['DEMO-REG-003', 'Personne DETRUIT', 'Registre marqué détruit'],
        ['DEMO-REG-004', 'Personne PANNE', 'Registre injoignable'],
    ];

    public function run(RequestTransitionService $transitions): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Jeu de démonstration interdit en production.');
        }

        $centre = CivilStatusCenter::query()->where('code', 'YDE-I-CEC')->firstOrFail();

        foreach (self::CASES as $index => [$numero, $nom, $description]) {
            $citoyen = User::updateOrCreate(
                ['email' => 'cas-'.($index + 1).'@phoenix.test'],
                [
                    'name' => $nom,
                    'password' => Hash::make(DemoAccountsSeeder::PASSWORD),
                    'role' => 'citizen',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            CitizenProfile::updateOrCreate(
                ['user_id' => $citoyen->id],
                [
                    'first_name' => 'Personne',
                    'last_name' => explode(' ', $nom, 2)[1] ?? 'DE TEST',
                    'birth_date' => '1990-06-01',
                    'birth_place' => 'Yaoundé',
                    'national_id_number' => $numero,
                    'phone' => '+237600000000',
                    'address' => 'Adresse de démonstration',
                    'completed_at' => now(),
                ]
            );

            $demande = ReissuanceRequest::withoutGlobalScopes()->create([
                'reference' => ReissuanceRequest::generateReference(),
                'user_id' => $citoyen->id,
                'civil_status_center_id' => $centre->id,
                'commune_id' => $centre->commune_id,
                'reason' => 'lost',
                'full_name_at_birth' => $nom,
                'date_of_birth' => '1990-06-01',
                'place_of_birth' => 'Yaoundé',
                'registration_year' => 1990,
                'father_name' => 'Père DEMO',
                'father_nationality' => 'Camerounaise',
                'mother_name' => 'Mère DEMO',
                'mother_nationality' => 'Camerounaise',
                'parents_address' => 'Adresse de démonstration',
                'consent_given_at' => now(),
            ]);
            $demande->forceFill(['submitted_at' => now()])->save();

            $transitions->transition($demande, RequestStatus::Pending, $citoyen);

            $this->command?->line("  {$demande->reference} — {$description}");
        }
    }
}
