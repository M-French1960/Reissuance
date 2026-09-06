<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le 7 du brief demande des tests exhaustifs : pour chaque couple
 * (statut, role), verifier que les transitions interdites sont refusees.
 *
 * Un test qui ne prouve que le cas heureux ne prouve rien.
 */
class StateMachineTest extends TestCase
{
    private RequestTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RequestTransitionService::class);
    }

    /**
     * Amene une demande dans l'etat voulu par le SEUL chemin legitime.
     *
     * On ne desactive jamais le declencheur pour preparer un cas de test :
     * cela demanderait un verrou exclusif que la transaction du test detient
     * deja, et surtout cela testerait un etat qu'aucun parcours reel ne
     * permet d'atteindre.
     */
    private function makeRequest(RequestStatus $status = RequestStatus::Draft): ReissuanceRequest
    {
        $center = CivilStatusCenter::factory()->create();
        $citizen = User::factory()->citizen()->create();

        $request = ReissuanceRequest::create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citizen->id,
            'civil_status_center_id' => $center->id,
            'commune_id' => $center->commune_id,
            'reason' => 'lost',
        ]);

        if ($status === RequestStatus::Draft) {
            return $request->refresh();
        }

        $request->forceFill(['submitted_at' => now()])->save();

        $officer = User::factory()->officer($center)->create();
        $mayor = User::factory()->mayor($center->commune)->create();

        $path = match ($status) {
            RequestStatus::Pending => [[RequestStatus::Pending, $citizen]],
            RequestStatus::UnderReview => [
                [RequestStatus::Pending, $citizen],
                [RequestStatus::UnderReview, $officer],
            ],
            RequestStatus::AwaitingSignature => [
                [RequestStatus::Pending, $citizen],
                [RequestStatus::UnderReview, $officer],
                [RequestStatus::AwaitingSignature, $officer],
            ],
            RequestStatus::Escalated => [
                [RequestStatus::Pending, $citizen],
                [RequestStatus::UnderReview, $officer],
                [RequestStatus::Escalated, $officer],
            ],
            RequestStatus::Signed => [
                [RequestStatus::Pending, $citizen],
                [RequestStatus::UnderReview, $officer],
                [RequestStatus::AwaitingSignature, $officer],
                [RequestStatus::Signed, $mayor],
            ],
            RequestStatus::Rejected => [
                [RequestStatus::Pending, $citizen],
                [RequestStatus::UnderReview, $officer],
                [RequestStatus::Rejected, $officer],
            ],
            RequestStatus::Draft => [],
        };

        foreach ($path as [$to, $actor]) {
            $this->service->transition($request, $to, $actor, 'Motif de test suffisamment long.');
        }

        return $request->refresh();
    }

    #[Test]
    public function le_chemin_legitime_complet_aboutit_a_signed(): void
    {
        $request = $this->makeRequest();
        $request->forceFill(['submitted_at' => now()])->save();

        $center = $request->center;
        $citizen = $request->citizen;
        $officer = User::factory()->officer($center)->create();
        $mayor = User::factory()->mayor($center->commune)->create();

        $this->service->transition($request, RequestStatus::Pending, $citizen);
        $this->service->transition($request, RequestStatus::UnderReview, $officer);
        $this->service->transition($request, RequestStatus::AwaitingSignature, $officer);
        $this->service->transition($request, RequestStatus::Signed, $mayor);

        $this->assertSame(RequestStatus::Signed, $request->refresh()->status);
        $this->assertSame(4, AuditLog::where('auditable_id', $request->id)->count());
    }

    /** T8 option A : le maire retourne un dossier prêt à signer à l'officier. */
    #[Test]
    public function le_maire_peut_retourner_un_dossier_pret_a_signer(): void
    {
        $request = $this->makeRequest(RequestStatus::AwaitingSignature);
        $mayor = User::factory()->mayor($request->center->commune)->create();

        $this->service->transition($request, RequestStatus::UnderReview, $mayor, 'Pièce illisible.');

        $this->assertSame(RequestStatus::UnderReview, $request->refresh()->status);
    }

    /**
     * Produit cartesien : tout couple absent de la table est refuse.
     *
     * @return iterable<string, array{RequestStatus, RequestStatus}>
     */
    public static function transitionsInterdites(): iterable
    {
        $allowed = RequestTransitionService::TRANSITIONS;

        foreach (RequestStatus::cases() as $from) {
            foreach (RequestStatus::cases() as $to) {
                if ($from === $to || isset($allowed[$from->value][$to->value])) {
                    continue;
                }

                yield "{$from->value} vers {$to->value}" => [$from, $to];
            }
        }
    }

    #[Test]
    #[DataProvider('transitionsInterdites')]
    public function toute_transition_hors_table_est_refusee(RequestStatus $from, RequestStatus $to): void
    {
        $request = $this->makeRequest($from);
        $actor = User::factory()->admin()->create();

        $this->expectException(DomainException::class);
        $this->service->transition($request, $to, $actor);
    }

    #[Test]
    public function le_role_est_verifie_en_plus_du_couple_de_statuts(): void
    {
        $request = $this->makeRequest(RequestStatus::AwaitingSignature);
        // La transition awaiting_signature -> signed existe, mais elle est
        // reservee au maire. Un officier ne doit pas pouvoir signer.
        $officer = User::factory()->officer($request->center)->create();

        $this->expectException(DomainException::class);
        $this->service->transition($request, RequestStatus::Signed, $officer);
    }

    /**
     * La barriere qui compte : meme en contournant le service, la base refuse.
     */
    #[Test]
    public function la_base_refuse_une_transition_interdite_meme_sans_passer_par_le_service(): void
    {
        $request = $this->makeRequest(RequestStatus::Draft);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Transition interdite/');

        DB::table('reissuance_requests')->where('id', $request->id)
            ->update(['status' => RequestStatus::Signed->value]);
    }

    #[Test]
    public function la_base_refuse_une_transition_sans_ligne_d_audit(): void
    {
        $request = $this->makeRequest(RequestStatus::Draft);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches("/aucune ligne d'audit/");

        // draft -> pending est autorise, mais sans audit prealable.
        DB::table('reissuance_requests')->where('id', $request->id)
            ->update(['status' => RequestStatus::Pending->value]);
    }

    /**
     * @return iterable<string, array{RequestStatus}>
     */
    public static function etatsTerminaux(): iterable
    {
        yield 'signed' => [RequestStatus::Signed];
        yield 'rejected' => [RequestStatus::Rejected];
    }

    #[Test]
    #[DataProvider('etatsTerminaux')]
    public function un_etat_terminal_n_a_aucune_sortie(RequestStatus $terminal): void
    {
        $request = $this->makeRequest($terminal);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/etat terminal/');

        DB::table('reissuance_requests')->where('id', $request->id)
            ->update(['status' => RequestStatus::UnderReview->value]);
    }

    /**
     * L'enumeration PHP et la table SQL doivent decrire la meme machine.
     * Si elles divergent, l'une des deux ment.
     */
    #[Test]
    public function le_service_et_la_table_allowed_transitions_concordent(): void
    {
        $inDatabase = DB::table('allowed_transitions')
            ->get()
            ->map(fn ($row): string => "{$row->from_status}>{$row->to_status}:{$row->actor_role}")
            ->sort()->values()->all();

        $inCode = [];
        foreach (RequestTransitionService::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to => $role) {
                $inCode[] = "{$from}>{$to}:{$role}";
            }
        }
        sort($inCode);

        $this->assertSame($inDatabase, $inCode);
    }
}
