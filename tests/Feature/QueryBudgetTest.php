<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le nombre de requetes SQL d'un ecran ne doit pas croitre avec le nombre de
 * lignes affichees.
 *
 * C'est la seule definition utile d'un N+1 : pas « il y a beaucoup de
 * requetes », mais « il y en a une de plus par ligne ». Un ecran mesure sur
 * trois lignes ne prouve rien ; on mesure donc DEUX fois, avec 3 puis 30
 * dossiers, et on compare.
 *
 * Le §8.3 du brief vise un reseau contraint : chaque aller-retour SQL est du
 * temps pendant lequel la page n'est pas rendue.
 */
class QueryBudgetTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    private User $maire;

    /** Compteur global : creerDossiers() est appele deux fois par test, et un
     *  numero de piece est unique en base (index aveugle). */
    private int $suivant = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
    }

    /** Compte les requetes emises pour rendre une URL. */
    private function requetesPour(User $acteur, string $url): int
    {
        $this->actingAs($acteur);

        // Une premiere visite chauffe ce qui doit l'etre (vues compilees,
        // configuration) : sans cela on compterait du bruit de demarrage.
        $this->get($url);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $n = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        if (getenv('PHOENIX_SHOW_QUERIES')) {
            fwrite(STDERR, sprintf("MESURE %s : %d requetes\n", $url, $n));
        }

        return $n;
    }

    /** @return list<ReissuanceRequest> */
    private function creerDossiers(int $combien, RequestStatus $cible): array
    {
        $dossiers = [];
        $transitions = app(RequestTransitionService::class);
        $workflow = app(VerificationWorkflow::class);

        for ($i = 0; $i < $combien; $i++) {
            $citoyen = User::factory()->citizen()->create();
            $citoyen->profile()->create([
                'first_name' => 'Personne', 'last_name' => 'DE TEST',
                'national_id_number' => 'DEMO-'.str_pad((string) (400000 + $this->suivant++), 9, '0', STR_PAD_LEFT),
                'completed_at' => now(),
            ]);

            $demande = ReissuanceRequest::withoutGlobalScopes()->create([
                'reference' => ReissuanceRequest::generateReference(),
                'user_id' => $citoyen->id,
                'civil_status_center_id' => $this->centre->id,
                'commune_id' => $this->centre->commune_id,
                'reason' => 'lost',
                'full_name_at_birth' => 'Personne DE TEST',
                'date_of_birth' => '1990-01-15',
                'place_of_birth' => 'Yaoundé',
                'registration_year' => 1990,
                'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
                'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
                'parents_address' => 'Adresse de test',
            ]);
            $demande->forceFill(['submitted_at' => now()->subMinutes($i)])->save();

            $transitions->transition($demande, RequestStatus::Pending, $citoyen);

            if ($cible !== RequestStatus::Pending) {
                $transitions->transition($demande, RequestStatus::UnderReview, $this->officier);
                $demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
                $demande->refresh();
            }

            if ($cible === RequestStatus::AwaitingSignature) {
                foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
                    $workflow->record($demande, $n, $this->officier, VerificationResult::Match);
                }
                $transitions->transition($demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);
            }

            $dossiers[] = $demande->refresh();
        }

        return $dossiers;
    }

    /**
     * Les dossiers sont PRIS EN CHARGE, pas simplement deposes.
     *
     * La vue affiche le nom de l'officier assigne. Avec des dossiers libres,
     * ce nom est nul, Eloquent n'interroge rien, et le test passerait au vert
     * sans jamais exercer la relation qui pourrait couter une requete par
     * ligne. Un jeu d'essai trop faible rend un test de N+1 sans objet.
     */
    #[Test]
    public function la_file_de_l_officier_ne_coute_pas_une_requete_de_plus_par_dossier(): void
    {
        $this->creerDossiers(3, RequestStatus::UnderReview);
        $petit = $this->requetesPour($this->officier, route('officer.queue'));

        $this->creerDossiers(27, RequestStatus::UnderReview);
        $grand = $this->requetesPour($this->officier, route('officer.queue'));

        $this->assertSame($petit, $grand, sprintf(
            'La file coute %d requetes pour 3 dossiers et %d pour 30 : le cout croit avec le nombre de lignes.',
            $petit, $grand
        ));
    }

    #[Test]
    public function le_tableau_de_signature_ne_coute_pas_une_requete_de_plus_par_dossier(): void
    {
        $this->creerDossiers(3, RequestStatus::AwaitingSignature);
        $petit = $this->requetesPour($this->maire, route('mayor.dashboard'));

        $this->creerDossiers(12, RequestStatus::AwaitingSignature);
        $grand = $this->requetesPour($this->maire, route('mayor.dashboard'));

        $this->assertSame($petit, $grand, sprintf(
            'Le tableau du maire coute %d requetes pour 3 dossiers et %d pour 15.',
            $petit, $grand
        ));
    }

    #[Test]
    public function la_liste_du_citoyen_ne_coute_pas_une_requete_de_plus_par_demande(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-450000001', 'completed_at' => now(),
        ]);

        $transitions = app(RequestTransitionService::class);

        $creer = function (int $combien) use ($citoyen, $transitions): void {
            for ($i = 0; $i < $combien; $i++) {
                $d = ReissuanceRequest::withoutGlobalScopes()->create([
                    'reference' => ReissuanceRequest::generateReference(),
                    'user_id' => $citoyen->id,
                    'civil_status_center_id' => $this->centre->id,
                    'commune_id' => $this->centre->commune_id,
                    'reason' => 'lost',
                    'full_name_at_birth' => 'Personne DE TEST',
                    'date_of_birth' => '1990-01-15',
                    'place_of_birth' => 'Yaoundé',
                    'registration_year' => 1990,
                    'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
                    'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
                    'parents_address' => 'Adresse de test',
                ]);
                $d->forceFill(['submitted_at' => now()])->save();
                $transitions->transition($d, RequestStatus::Pending, $citoyen);
            }
        };

        $creer(2);
        $petit = $this->requetesPour($citoyen, route('citizen.requests.index'));

        $creer(18);
        $grand = $this->requetesPour($citoyen, route('citizen.requests.index'));

        $this->assertSame($petit, $grand, sprintf(
            'La liste du citoyen coute %d requetes pour 2 demandes et %d pour 20.',
            $petit, $grand
        ));
    }

    #[Test]
    public function le_journal_d_audit_ne_coute_pas_une_requete_de_plus_par_entree(): void
    {
        $admin = User::factory()->admin()->create();

        $this->creerDossiers(3, RequestStatus::Pending);
        $petit = $this->requetesPour($admin, route('admin.audit.index'));

        $this->creerDossiers(20, RequestStatus::AwaitingSignature);
        $grand = $this->requetesPour($admin, route('admin.audit.index'));

        $this->assertSame($petit, $grand, sprintf(
            "Le journal coute %d requetes pour peu d'entrees et %d pour beaucoup.",
            $petit, $grand
        ));
    }

    /**
     * Aucune collection non bornee (8.5 du brief).
     *
     * Un controleur qui rend `->get()` sur une table qui grandit finit par
     * rendre une page de plusieurs mega-octets a un telephone sur reseau
     * contraint. La regle est simple : tout ce qui liste, pagine.
     */
    #[Test]
    public function aucun_ecran_de_liste_ne_rend_une_collection_non_bornee(): void
    {
        $listes = [
            'Officer/QueueController.php',
            'Mayor/DashboardController.php',
            'Admin/UserController.php',
            'Admin/AuditLogController.php',
            'Citizen/RequestTrackingController.php',
            'NotificationController.php',
        ];

        $fautifs = [];

        foreach ($listes as $chemin) {
            $source = file_get_contents(app_path('Http/Controllers/'.$chemin));

            if (! str_contains($source, 'paginate(')) {
                $fautifs[] = $chemin;
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['Ces controleurs de liste ne paginent pas :'],
            $fautifs,
        )));
    }

    #[Test]
    public function la_liste_des_comptes_ne_coute_pas_une_requete_de_plus_par_compte(): void
    {
        $admin = User::factory()->admin()->create();

        $petit = $this->requetesPour($admin, route('admin.users.index'));

        User::factory()->count(20)->officer($this->centre)->create();
        $grand = $this->requetesPour($admin, route('admin.users.index'));

        $this->assertSame($petit, $grand, sprintf(
            'La liste des comptes coute %d requetes a vide et %d avec 20 comptes.',
            $petit, $grand
        ));
    }
}
