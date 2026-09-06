<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CitizenProfile;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes de demonstration pour les quatre roles.
 *
 * Garde-fou n1 du projet et D-004 : aucune donnee reelle de citoyen. Toutes
 * les identites ci-dessous sont explicitement fictives — domaine .test reserve
 * par la RFC 2606, numeros de piece hors de tout format reel.
 */
class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'motdepasse-demo-a-changer';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Les comptes de démonstration ne doivent jamais être créés en production.'
            );
        }

        $center = CivilStatusCenter::query()->where('code', 'YDE-I-CEC')->firstOrFail();
        $otherCenter = CivilStatusCenter::query()->where('code', 'YDE-II-CEC')->firstOrFail();
        $commune = Commune::query()->where('code', 'YDE-I')->firstOrFail();

        $admin = $this->makeUser('Administrateur DEMO', 'admin@phoenix.test', UserRole::Admin);

        // Un officier par centre : permet de tester le refus d'acces croise
        // entre centres (test R3 de docs/PERMISSIONS.md).
        $this->makeUser('Officier DEMO Yaoundé I', 'officier@phoenix.test', UserRole::Officer, [
            'civil_status_center_id' => $center->id,
        ]);
        $this->makeUser('Officier DEMO Yaoundé II', 'officier2@phoenix.test', UserRole::Officer, [
            'civil_status_center_id' => $otherCenter->id,
        ]);

        $this->makeUser('Maire DEMO Yaoundé I', 'maire@phoenix.test', UserRole::Mayor, [
            'commune_id' => $commune->id,
        ]);

        $citizen = $this->makeUser('Citoyen DEMO', 'citoyen@phoenix.test', UserRole::Citizen);

        CitizenProfile::updateOrCreate(
            ['user_id' => $citizen->id],
            [
                'first_name' => 'Citoyen',
                'last_name' => 'DEMO',
                'birth_date' => '1990-01-15',
                'birth_place' => 'Yaoundé',
                // Format volontairement irreel : prefixe DEMO.
                'national_id_number' => 'DEMO-000000001',
                'phone' => '+237600000000',
                'address' => 'Adresse de démonstration',
                'completed_at' => now(),
            ]
        );

        $this->command?->newLine();
        $this->command?->info('Comptes de démonstration (mot de passe commun) :');
        $this->command?->table(
            ['Rôle', 'Adresse', 'Mot de passe'],
            [
                ['Administrateur', 'admin@phoenix.test', self::PASSWORD],
                ['Officier (Yaoundé I)', 'officier@phoenix.test', self::PASSWORD],
                ['Officier (Yaoundé II)', 'officier2@phoenix.test', self::PASSWORD],
                ['Maire (Yaoundé I)', 'maire@phoenix.test', self::PASSWORD],
                ['Citoyen', 'citoyen@phoenix.test', self::PASSWORD],
            ]
        );
        $this->command?->warn('Comptes de démonstration : à ne jamais déployer tels quels.');

        unset($admin);
    }

    /** @param  array<string, mixed>  $extra */
    private function makeUser(string $name, string $email, UserRole $role, array $extra = []): User
    {
        return User::updateOrCreate(['email' => $email], array_merge([
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role->value,
            'status' => 'active',
            'email_verified_at' => now(),
            // La contrainte users_official_2fa_check refuse un compte officiel
            // actif sans 2FA confirmee : les comptes de demo la satisfont.
            'two_factor_confirmed_at' => $role->requiresTwoFactor() ? now() : null,
        ], $extra));
    }
}
