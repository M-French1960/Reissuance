<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CitizenProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CitizenProfileTest extends TestCase
{
    private function makeProfile(string $number): CitizenProfile
    {
        return CitizenProfile::create([
            'user_id' => User::factory()->citizen()->create()->id,
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'national_id_number' => $number,
        ]);
    }

    #[Test]
    public function le_numero_de_piece_n_est_jamais_stocke_en_clair(): void
    {
        $profile = $this->makeProfile('DEMO-123456789');

        $brut = DB::table('citizen_profiles')->where('id', $profile->id)->value('national_id_number');

        $this->assertNotSame('DEMO-123456789', $brut);
        $this->assertStringNotContainsString('123456789', (string) $brut);
    }

    #[Test]
    public function le_numero_est_relisible_par_le_modele(): void
    {
        $profile = $this->makeProfile('DEMO-123456789');

        $this->assertSame('DEMO123456789', $profile->fresh()->national_id_number);
    }

    #[Test]
    public function la_recherche_par_numero_exact_fonctionne(): void
    {
        $profile = $this->makeProfile('DEMO-123456789');

        $trouve = CitizenProfile::query()->whereNationalId('demo 123 456 789')->first();

        $this->assertNotNull($trouve, 'La recherche doit absorber les variations de saisie.');
        $this->assertSame($profile->id, $trouve->id);
    }

    #[Test]
    public function le_numero_et_son_empreinte_ne_peuvent_pas_diverger(): void
    {
        $profile = $this->makeProfile('DEMO-111111111');
        $avant = DB::table('citizen_profiles')->where('id', $profile->id)->value('national_id_hash');

        $profile->update(['national_id_number' => 'DEMO-222222222']);

        $apres = DB::table('citizen_profiles')->where('id', $profile->id)->value('national_id_hash');

        $this->assertNotSame($avant, $apres, "L'empreinte doit suivre toute mise à jour du numéro.");
        $this->assertNotNull(CitizenProfile::query()->whereNationalId('DEMO-222222222')->first());
    }

    #[Test]
    public function le_numero_est_masque_dans_la_serialisation(): void
    {
        $profile = $this->makeProfile('DEMO-999999999');

        $json = $profile->toArray();

        $this->assertArrayNotHasKey('national_id_number', $json);
        $this->assertArrayNotHasKey('national_id_hash', $json);
    }
}
