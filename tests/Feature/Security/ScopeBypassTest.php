<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
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
     * Le point unique lui-meme doit rester unique DANS son fichier.
     *
     * Sans cela, le test ci-dessus se contenterait d'un fichier exempte dans
     * lequel on pourrait en ajouter autant qu'on veut.
     */
    #[Test]
    public function le_point_unique_ne_contourne_la_portee_qu_une_fois(): void
    {
        $contenu = (string) file_get_contents(base_path(self::POINT_UNIQUE));

        // On compte les APPELS, pas les mentions : le commentaire qui explique
        // la methode cite l'idiome, et c'est voulu.
        $this->assertSame(
            1,
            preg_match_all('/withoutGlobalScopes\s*\(/', $contenu),
            'Le point de contournement doit rester unique dans son propre fichier.'
        );
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
