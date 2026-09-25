<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Un seul point de contournement de la portee de visibilite, et il est audite.
 *
 * POURQUOI CE TEST EXISTE. Servir une piece d'identite ou un acte signe oblige
 * a charger la demande hors portee, puisque la portee la masquerait avant
 * qu'on puisse poser la question de l'autorisation. Le contournement est donc
 * legitime — mais tout ce qui separe alors le chargement de la fuite est UNE
 * LIGNE d'autorisation, que rien ne garde. Supprimee par megarde, elle ouvre
 * la consultation de n'importe quelle piece d'identite a n'importe quel compte
 * authentifie, sans aucune erreur.
 *
 * Tant que la base etait PostgreSQL, la securite au niveau des lignes aurait
 * pu rattraper cet oubli. MySQL n'en a pas (D-051) : il ne reste que la
 * discipline du code. Ce test la rend verifiable — l'idiome brut
 * `withoutGlobalScopes()` ne doit apparaitre nulle part ailleurs dans `app/`,
 * de sorte qu'il y ait UN endroit a relire, et un seul.
 *
 * Voir docs/RLS.md 7.3.
 */
class ScopeBypassTest extends TestCase
{
    /** Le seul fichier de `app/` autorise a contourner la portee. */
    private const POINT_UNIQUE = 'app/Models/ReissuanceRequest.php';

    /**
     * Les methodes auditees qui ont le droit de contourner la portee.
     *
     * Il y en a trois, et il ne doit pas y en avoir une quatrieme sans qu'on
     * l'ait voulu : chacune est documentee dans le modele, et chacune porte sa
     * propre protection — une autorisation immediate pour la premiere, une
     * liste blanche de colonnes sans donnee d'identite pour la seconde, et
     * pour la troisieme le fait qu'elle ne rende AUCUNE colonne d'une demande,
     * seulement des agregats par centre (D-085).
     *
     * @var list<string>
     */
    private const METHODES_AUDITEES = [
        'loadForAuthorization',
        'assignmentsForAdministration',
        'aggregatesForAdministration',
    ];

    #[Test]
    public function le_contournement_de_portee_n_apparait_qu_a_un_seul_endroit(): void
    {
        $coupables = [];

        foreach (self::fichiersPhpDeApp() as $chemin => $contenu) {
            if ($chemin === self::POINT_UNIQUE) {
                continue;
            }

            if (str_contains($contenu, 'withoutGlobalScope')) {
                $coupables[] = $chemin;
            }
        }

        $this->assertSame(
            [],
            $coupables,
            "Contournement de la portée globale hors du point unique :\n  ".implode("\n  ", $coupables)
                ."\n\nUtilisez ReissuanceRequest::loadForAuthorization(), et autorisez la demande "
                .'immédiatement après le chargement.'
        );
    }

    /**
     * Le fichier exempte ne contourne la portee que dans les methodes prevues.
     *
     * Sans cela, le test ci-dessus se contenterait d'un fichier exempte dans
     * lequel on pourrait en ajouter autant qu'on veut.
     */
    #[Test]
    public function le_fichier_exempte_ne_contourne_la_portee_que_dans_les_methodes_auditees(): void
    {
        $contenu = (string) file_get_contents(base_path(self::POINT_UNIQUE));

        // On compte les APPELS, pas les mentions : les commentaires qui
        // expliquent ces methodes citent l'idiome, et c'est voulu.
        $this->assertSame(
            count(self::METHODES_AUDITEES),
            preg_match_all('/withoutGlobalScopes\s*\(/', $contenu),
            'Un contournement de portée est apparu hors des méthodes auditées.'
        );

        foreach (self::METHODES_AUDITEES as $methode) {
            $this->assertStringContainsString(
                "function {$methode}(",
                $contenu,
                "La méthode auditée {$methode}() a disparu : la liste est à revoir."
            );
        }
    }

    /**
     * L'administration ne lit AUCUNE donnee d'identite, colonne par colonne.
     *
     * C'est la protection de `assignmentsForAdministration()` : elle contourne
     * la portee, donc sa liste blanche est tout ce qui separe la gestion des
     * comptes de la consultation des dossiers.
     */
    #[Test]
    public function la_lecture_administrative_ne_porte_aucune_donnee_d_identite(): void
    {
        $interdites = [
            'full_name_at_birth', 'date_of_birth', 'place_of_birth',
            'father_name', 'mother_name', 'parents_address',
            'original_certificate_number', 'registration_year',
            'father_nationality', 'mother_nationality', 'user_id',
        ];

        foreach ($interdites as $colonne) {
            $this->assertNotContains(
                $colonne,
                ReissuanceRequest::ADMINISTRATION_COLUMNS,
                "La colonne {$colonne} ne doit jamais être lisible par l'administration."
            );
        }
    }

    /**
     * La methode fait bien ce qu'elle annonce : elle rend une demande hors
     * perimetre.
     *
     * Un test structurel seul serait satisfait par une methode qui ne
     * contourne rien. Celui-ci exerce le comportement.
     */
    #[Test]
    public function la_methode_rend_bien_une_demande_hors_perimetre(): void
    {
        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => User::factory()->citizen()->create()->id,
            'civil_status_center_id' => ($centre = CivilStatusCenter::factory()->create())->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
        ]);

        // Un administrateur ne voit AUCUNE demande via la portee.
        $this->actingAs(User::factory()->admin()->create());

        $this->assertNull(
            ReissuanceRequest::find($demande->id),
            'La portée devrait masquer cette demande : sinon le test ne prouve rien.'
        );

        $this->assertSame(
            $demande->id,
            ReissuanceRequest::loadForAuthorization($demande->id)->id,
            'loadForAuthorization() doit rendre la demande malgré la portée.'
        );
    }

    /**
     * Les agregats ne rendent aucune donnee d'une demande.
     *
     * C'est la protection de `aggregatesForAdministration()` : elle contourne la
     * portee, et ce qui la rend defendable est qu'elle ne selectionne aucune
     * colonne de la table — un identifiant de centre, un compte, une date.
     * Le test lit les cles reellement rendues, pas la promesse du commentaire.
     */
    #[Test]
    public function les_agregats_administratifs_ne_rendent_que_des_comptes(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        $citoyen = User::factory()->citizen()->create();

        // L'etat passe par la machine a etats : un declencheur de base refuse
        // une transition sans ligne d'audit correspondante.
        $demande = ReissuanceRequest::factory()->submitted()->create([
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
        ]);

        app(RequestTransitionService::class)->transition($demande, RequestStatus::Pending, $citoyen);

        $this->actingAs(User::factory()->admin()->create());

        $ligne = ReissuanceRequest::aggregatesForAdministration()->get()->first();

        $this->assertNotNull($ligne, "L'agrégat doit rendre la ligne malgré la portée.");

        $this->assertSame(
            ['civil_status_center_id', 'received', 'in_flight', 'oldest'],
            array_keys($ligne->getAttributes()),
            'Les agrégats administratifs ne doivent rendre que le centre et des comptes.'
        );
    }

    /** @return array<string, string> chemin relatif => contenu */
    private static function fichiersPhpDeApp(): array
    {
        $fichiers = [];
        $racine = base_path('app');

        $parcours = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine));

        foreach ($parcours as $fichier) {
            if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                continue;
            }

            $chemin = str_replace(base_path().'/', '', $fichier->getPathname());
            $fichiers[$chemin] = (string) file_get_contents($fichier->getPathname());
        }

        return $fichiers;
    }
}
