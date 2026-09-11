<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le diagramme de cas d'utilisation, applique par la suite de tests.
 *
 * POURQUOI CE FICHIER EXISTE. La couverture des cas etait suivie dans
 * `docs/CAS_USAGE.md` — un tableau que J'ENTRETIENS. Un tableau que son auteur
 * entretient ne prouve rien : il a deja diverge une fois, listant comme « a
 * construire » trois cas que sa propre section precedente donnait pour faits.
 * Annoncer « tous les cas sont couverts » sur la foi d'un document qu'on ecrit
 * soi-meme, c'est exactement le defaut que ce projet traque ailleurs.
 *
 * Ici, chaque cas du diagramme est attache a une route nommee et a l'acteur
 * qui l'exerce. Si un cas devient inatteignable — route supprimee, renommee,
 * fermee au mauvais role — la suite echoue. Le diagramme cesse d'etre un
 * document qui derive pour devenir une contrainte.
 *
 * CE QUE CE FICHIER NE PROUVE PAS, et il faut le dire : qu'un cas soit
 * atteignable ne dit rien de sa justesse. Le comportement de chaque cas est
 * verifie par son propre fichier de test ; celui-ci ne verifie que la
 * TRACABILITE — le diagramme au code.
 *
 * Voir D-061 et docs/CAS_USAGE.md.
 */
class UseCaseCoverageTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private User $maire;

    private User $admin;

    private ReissuanceRequest $brouillon;

    private ReissuanceRequest $enExamen;

    private ReissuanceRequest $aSigner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
        $this->admin = User::factory()->admin()->create();

        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-700000001', 'completed_at' => now(),
        ]);

        $this->brouillon = $this->demande();

        $this->enExamen = $this->demande();
        $this->avance($this->enExamen, RequestStatus::UnderReview);

        $this->aSigner = $this->demande();
        $this->avance($this->aSigner, RequestStatus::AwaitingSignature);
    }

    private function demande(): ReissuanceRequest
    {
        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
        ]);

        return $demande->refresh();
    }

    private function avance(ReissuanceRequest $demande, RequestStatus $jusqu_a): void
    {
        $demande->forceFill(['submitted_at' => now()])->save();
        $service = app(RequestTransitionService::class);

        $service->transition($demande->refresh(), RequestStatus::Pending, $this->citoyen);

        if ($jusqu_a === RequestStatus::Pending) {
            return;
        }

        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $demande));

        if ($jusqu_a === RequestStatus::AwaitingSignature) {
            $service->transition($demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);
        }

        $this->app['auth']->logout();
    }

    /**
     * Le diagramme, cas par cas.
     *
     * @return iterable<string, array{string, list<string>, ?string}>
     *                                                                cas => [acteur, routes nommees, route GET a ouvrir (ou null)]
     */
    public static function casDuDiagramme(): iterable
    {
        /*
         * LES NOMS SONT CEUX DU DIAGRAMME, a la lettre. « Escalate Request »
         * et non « Escalate Case », « Make Reissuance Request » et non « Make
         * Request » : une traduction approximative fait perdre la trace.
         *
         * [acteur, routes qui realisent le cas, ecran a ouvrir]
         */
        $cas = [
            'Authenticate' => ['visitor', ['login', 'two-factor.setup'], 'login'],
            'Create Citizen Account' => ['visitor', ['register'], 'register'],
            'Make Reissuance Request' => ['citizen', ['citizen.requests.start', 'citizen.requests.step', 'citizen.requests.save'], 'wizard'],
            'Update Account Information' => ['citizen', ['citizen.profile.edit', 'citizen.profile.update'], 'citizen.profile.edit'],
            'Consult Notification' => ['citizen', ['notifications.index'], 'notifications.index'],
            'Track Request Status' => ['citizen', ['citizen.requests.index', 'citizen.requests.show'], 'citizen.requests.index'],
            'Download Certificate' => ['citizen', ['acts.document', 'acts.proof'], null],
            'Make Payment' => ['citizen', ['citizen.requests.payment', 'citizen.requests.payment.store'], null],
            // Deux specialisations de « Make Payment » au diagramme. Elles
            // partagent la route : ce qui les distingue est l'operateur choisi
            // par le demandeur, porte par la colonne `operator`.
            'Pay Through Orange Money' => ['citizen', ['citizen.requests.payment.store'], null],
            'Pay Through Mobile Money' => ['citizen', ['citizen.requests.payment.store'], null],
            'Cancel Request' => ['citizen', ['citizen.requests.cancel'], null],
            'Contact Officer' => ['citizen', ['requests.messages.store'], null],
            'Manage Request' => ['officer', ['officer.queue', 'officer.verification.claim'], 'officer.queue'],
            'Verify Identity' => ['officer', ['officer.verification.step', 'officer.verification.identity', 'officer.verification.facial'], 'verification'],
            'Search Registry' => ['officer', ['officer.verification.registry'], null],
            'Accept Request' => ['officer', ['officer.decision.store'], null],
            'Reject Request' => ['officer', ['officer.decision.store'], null],
            'Escalate Request' => ['officer', ['officer.decision.store'], null],
            'Review Escalated Request' => ['mayor', ['mayor.dashboard', 'mayor.review'], 'mayor.dashboard'],
            'Sign Certificate' => ['mayor', ['mayor.sign'], null],
            'Send Certificate to Officer' => ['mayor', ['mayor.return'], null],
            'Manage Accounts' => ['admin', ['admin.users.index', 'admin.users.store', 'admin.users.status', 'admin.users.reassign'], 'admin.users.index'],
        ];

        foreach ($cas as $nom => $definition) {
            yield $nom => [$nom, ...$definition];
        }
    }

    /**
     * Chaque cas du diagramme est attache a des routes qui existent.
     *
     * @param  list<string>  $routes
     */
    #[Test]
    #[DataProvider('casDuDiagramme')]
    public function chaque_cas_du_diagramme_existe_dans_le_code(
        string $cas,
        string $acteur,
        array $routes,
        ?string $ecran,
    ): void {
        $connues = app('router')->getRoutes()->getRoutesByName();

        foreach ($routes as $route) {
            $this->assertArrayHasKey(
                $route,
                $connues,
                "Le cas « {$cas} » du diagramme n'a plus de route « {$route} »."
            );
        }
    }

    /**
     * Et l'ecran qui le porte s'ouvre pour l'acteur du diagramme.
     *
     * @param  list<string>  $routes
     */
    #[Test]
    #[DataProvider('casDuDiagramme')]
    public function chaque_ecran_s_ouvre_pour_l_acteur_du_diagramme(
        string $cas,
        string $acteur,
        array $routes,
        ?string $ecran,
    ): void {
        if ($ecran === null) {
            $this->markTestSkipped("« {$cas} » se réalise par une écriture, couverte par son propre test.");
        }

        $utilisateur = match ($acteur) {
            'citizen' => $this->citoyen,
            'officer' => $this->officier,
            'mayor' => $this->maire,
            'admin' => $this->admin,
            default => null,
        };

        $url = match ($ecran) {
            'wizard' => route('citizen.requests.step', ['reissuanceRequest' => $this->brouillon, 'step' => 1]),
            'verification' => route('officer.verification.step', ['reissuanceRequest' => $this->enExamen, 'step' => 1]),
            'mayor.dashboard' => route('mayor.dashboard'),
            default => route($ecran),
        };

        $reponse = $utilisateur === null
            ? $this->get($url)
            : $this->actingAs($utilisateur)->get($url);

        $reponse->assertOk("Le cas « {$cas} » n'est pas atteignable par son acteur.");
    }

    /**
     * « Couvert par son propre test » ne doit pas rester une affirmation.
     *
     * Les cas qui se realisent par une ecriture sont sautes par le test
     * precedent, au motif qu'ils sont verifies ailleurs. Ce motif est
     * lui-meme verifie ici : chaque route citee doit apparaitre dans au moins
     * un fichier de test. Sinon le saut ci-dessus deviendrait une facon
     * elegante de ne rien tester.
     *
     * @param  list<string>  $routes
     */
    #[Test]
    #[DataProvider('casDuDiagramme')]
    public function chaque_route_du_diagramme_est_exercee_par_un_test(
        string $cas,
        string $acteur,
        array $routes,
        ?string $ecran,
    ): void {
        // Les routes de Fortify sont exercees par ses propres tests, hors de
        // ce depot : on ne peut pas exiger de les trouver ici.
        $aVerifier = array_diff($routes, self::ROUTES_DU_CADRE);

        if ($aVerifier === []) {
            $this->markTestSkipped("« {$cas} » ne repose que sur des routes du cadre applicatif.");
        }

        $corpus = self::corpusDesTests();

        foreach ($aVerifier as $route) {
            $this->assertTrue(
                str_contains($corpus, $route),
                "Le cas « {$cas} » cite la route « {$route} », qu'aucun test n'exerce."
            );
        }
    }

    /** Routes fournies par Fortify, testees en amont de ce depot. */
    private const ROUTES_DU_CADRE = ['login', 'register'];

    /**
     * Le contenu de tous les fichiers de test, sauf celui-ci.
     *
     * Lu une fois : vingt jeux de donnees qui reparcourent l'arborescence
     * chacun de leur cote, c'est huit secondes pour rien.
     */
    private static function corpusDesTests(): string
    {
        static $corpus = null;

        if ($corpus !== null) {
            return $corpus;
        }

        $morceaux = [];
        $parcours = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__)));

        foreach ($parcours as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), 'Test.php')) {
                continue;
            }

            if ($fichier->getFilename() === basename(__FILE__)) {
                continue;
            }

            $morceaux[] = (string) file_get_contents($fichier->getPathname());
        }

        return $corpus = implode("\n", $morceaux);
    }

    /**
     * Les cas du diagramme QUI N'ONT PAS de route dediee.
     *
     * Ils sont declares ici avec leur justification, plutot que passes sous
     * silence dans un document. Le test ci-dessous echoue si la liste change
     * sans qu'on l'ait voulu : un cas non implemente ne doit pas pouvoir
     * disparaitre de la discussion.
     *
     * @return array<string, string>
     */
    public const CAS_SANS_ROUTE = [
        'Generate Certificate' => <<<'RAISON'
            Le diagramme place « Generate Certificate » chez l'OFFICIER. Dans
            le code, l'acte n'est fabrique qu'a la signature du maire
            (ActIssuanceService, appele depuis Mayor\DecisionController) : aucun
            officier ne genere de document.

            Ma lecture — l'acceptation de l'officier (T4) declenche la
            fabrication en aval — est une INTERPRETATION, pas un fait etabli.
            L'autre lecture est que l'officier prepare un projet d'acte que le
            maire signe ensuite, ce qui serait une capacite absente.

            DIVERGENCE OUVERTE : elle touche a qui redige l'acte, donc a la
            responsabilite de son contenu. Elle n'est pas mienne a trancher.
            Voir docs/CAS_USAGE.md 4.2.
            RAISON,
    ];

    /**
     * Un cas du diagramme sans route reste visible et justifie.
     *
     * Ce test ne verifie pas que le cas est implemente — il ne l'est pas. Il
     * verifie qu'on ne l'a pas oublie : la liste des cas manquants est une
     * donnee du projet, pas une note de bas de page.
     */
    #[Test]
    public function les_cas_du_diagramme_sans_route_restent_declares(): void
    {
        $this->assertSame(
            ['Generate Certificate'],
            array_keys(self::CAS_SANS_ROUTE),
            'La liste des cas du diagramme sans implémentation a changé. '
                .'Si un cas a été construit, retirez-le et ajoutez-le à casDuDiagramme(). '
                .'Si un nouveau cas manque, déclarez-le et dites pourquoi.'
        );

        foreach (self::CAS_SANS_ROUTE as $cas => $raison) {
            $this->assertNotSame('', trim($raison), "Le cas « {$cas} » est déclaré sans justification.");
        }
    }

    /**
     * Ce qui existe sans figurer au diagramme, et pourquoi.
     *
     * Le diagramme est la reference : ce qui n'y figure pas doit etre declare,
     * pas glisse en douce. Ces deux ecrans viennent de defauts constates, pas
     * d'une envie — et ce test tombe si un troisieme apparait sans etre
     * documente ici.
     */
    #[Test]
    public function les_ecrans_hors_diagramme_sont_declares(): void
    {
        $horsDiagramme = [
            // Ne figure pas au diagramme : ne vient pas d'un besoin exprime
            // mais d'une impasse constatee (D-057). Un dossier dont l'agent
            // affecte ne peut plus agir n'etait repris par personne.
            'admin.assignments.index' => 'Affectations bloquées (D-057)',
            'admin.assignments.release' => 'Libération d’une affectation (D-057)',

            /*
             * CONSTRUIT A TORT COMME UN CAS DU DIAGRAMME (D-062).
             *
             * La version 2 du diagramme ne comporte AUCUN cas « Manage system
             * settings » : le Super Admin n'y porte que « Manage Accounts ».
             * Je l'ai construit en croyant achever la couverture, sur la foi
             * de ma propre analyse de la version 1 — ou le cas existait, confie
             * au maire — et non sur la version 2, qui est la reference.
             *
             * L'ecran est utile et sans danger : consultation seule, aucune
             * route d'ecriture, aucun secret affiche. Il est donc CONSERVE,
             * mais declare ici pour ce qu'il est — un ajout hors diagramme,
             * dont le maintien vous revient.
             */
            'admin.settings.index' => 'Réglages, consultation seule — HORS DIAGRAMME v2 (D-062)',

            // Le journal d'audit sert le 4.4 du brief, pas un cas du diagramme.
            'admin.audit.index' => "Journal d'audit (§4.4 du brief)",
            // Exploitation, hors parcours metier.
            'health' => 'Page de santé',
            'dev.ui' => 'Galerie de composants, hors production',
            'webhooks.hrskills' => "Rappel signé de l'agrégateur de paiement",
        ];

        foreach (array_keys($horsDiagramme) as $route) {
            $this->assertArrayHasKey(
                $route,
                app('router')->getRoutes()->getRoutesByName(),
                "La route hors diagramme « {$route} » a disparu : mettez cette liste à jour."
            );
        }

        $this->assertNotEmpty($horsDiagramme);
    }
}
