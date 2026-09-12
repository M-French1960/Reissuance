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
use Laravel\Fortify\Fortify;

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
                // Format volontairement irreel : prefixe DEMO. Ce numero-ci
                // declenche le cas nominal des adaptateurs factices ; les
                // autres cas sont exerces par DemoRequestsSeeder.
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
        /*
         * Les codes d'authentification des comptes officiels.
         *
         * Affiches, jamais ecrits : depuis D-069, signer un acte exige de
         * ressaisir son code, et sans ces secrets le maire de demonstration ne
         * pourrait pas signer. Ils se collent dans une application
         * d'authentification, ou se calculent avec `oathtool --totp -b <clef>`.
         */
        $this->command?->newLine();
        $this->command?->info("Clefs d'authentification (à coller dans une application TOTP) :");
        $this->command?->table(
            ['Compte', 'Clef TOTP'],
            array_map(
                fn (string $courriel): array => [$courriel, self::demoSecret($courriel)],
                ['admin@phoenix.test', 'officier@phoenix.test', 'officier2@phoenix.test', 'maire@phoenix.test'],
            ),
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
            /*
             * ET UN VRAI SECRET, depuis D-069.
             *
             * Marquer la 2FA « confirmee » sans poser de secret produisait un
             * compte incoherent : la contrainte etait satisfaite, mais aucun
             * code n'existait. Depuis que signer exige de ressaisir son code,
             * le maire de demonstration ne pouvait plus signer du tout.
             *
             * Chiffre comme Fortify le fait, parce que le cast `encrypted` du
             * modele ajoute sa propre couche par-dessus.
             */
            'two_factor_secret' => $role->requiresTwoFactor()
                ? Fortify::currentEncrypter()->encrypt(self::demoSecret($email))
                : null,
        ], $extra));
    }

    /**
     * Le secret TOTP d'un compte de demonstration.
     *
     * DERIVE DE APP_KEY, et non tire au hasard : le seeder est rejouable, et
     * un secret different a chaque execution obligerait a reconfigurer
     * l'application d'authentification a chaque `db:seed`. Derive de APP_KEY,
     * il est stable pour une installation et different d'une installation a
     * l'autre — donc rien d'exploitable n'entre dans le depot.
     */
    private static function demoSecret(string $email): string
    {
        $graine = hash_hmac('sha256', 'phoenix-demo-totp:'.$email, (string) config('app.key'), true);

        // Base32 sur l'alphabet RFC 4648, 32 caracteres : le format attendu
        // par les applications d'authentification.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        foreach (str_split(substr($graine, 0, 20)) as $octet) {
            $secret .= $alphabet[ord($octet) % 32];
            $secret .= $alphabet[(ord($octet) >> 3) % 32];
        }

        return substr($secret, 0, 32);
    }
}
