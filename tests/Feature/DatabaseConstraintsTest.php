<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les contraintes qui rendent certains etats impossibles a creer, y compris
 * par un bug applicatif ou une requete SQL directe (docs/DATA_MODEL.md 2.2).
 */
class DatabaseConstraintsTest extends TestCase
{
    private function insertUser(array $overrides = []): void
    {
        DB::table('users')->insert(array_merge([
            'name' => 'Personne DE TEST',
            'email' => 'contrainte-'.uniqid().'@example.test',
            'password' => 'x',
            'role' => 'citizen',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function un_officier_sans_centre_est_impossible(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_role_scope_check/');

        $this->insertUser(['role' => 'officer', 'two_factor_confirmed_at' => now()]);
    }

    #[Test]
    public function un_maire_sans_commune_est_impossible(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_role_scope_check/');

        $this->insertUser(['role' => 'mayor', 'two_factor_confirmed_at' => now()]);
    }

    #[Test]
    public function un_citoyen_rattache_a_un_centre_est_impossible(): void
    {
        $center = CivilStatusCenter::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_role_scope_check/');

        $this->insertUser(['role' => 'citizen', 'civil_status_center_id' => $center->id]);
    }

    /** 4.1 du brief : 2FA obligatoire pour les roles officiels. */
    #[Test]
    public function un_compte_officiel_actif_sans_2fa_est_impossible(): void
    {
        $center = CivilStatusCenter::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_official_2fa_check/');

        $this->insertUser([
            'role' => 'officer',
            'civil_status_center_id' => $center->id,
            'two_factor_confirmed_at' => null,
        ]);
    }

    #[Test]
    public function un_statut_de_demande_invente_est_impossible(): void
    {
        $center = CivilStatusCenter::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/reissuance_requests_status_check/');

        DB::table('reissuance_requests')->insert([
            'reference' => 'TEST-'.uniqid(),
            'user_id' => User::factory()->citizen()->create()->id,
            'civil_status_center_id' => $center->id,
            'commune_id' => $center->commune_id,
            'status' => 'approuve_en_douce',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function une_decision_motivable_sans_motif_est_impossible(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/request_decisions_reason_required_check/');

        $center = CivilStatusCenter::factory()->create();
        $officer = User::factory()->officer($center)->create();
        $request = ReissuanceRequest::factory()->create();

        DB::table('request_decisions')->insert([
            'request_id' => $request->id,
            'actor_id' => $officer->id,
            'actor_role' => 'officer',
            'decision' => 'rejected',
            'reason' => null,
            'from_status' => 'under_review',
            'to_status' => 'rejected',
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function le_referentiel_des_transitions_est_en_lecture_seule_pour_l_application(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/permission denied/i');

        DB::table('allowed_transitions')->insert([
            'from_status' => 'draft', 'to_status' => 'signed',
            'actor_role' => 'citizen', 'label' => 'X',
        ]);
    }
}
