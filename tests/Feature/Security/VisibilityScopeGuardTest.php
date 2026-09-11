<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\ReissuanceRequest;
use App\Models\Scopes\RequestVisibilityScope;
use App\Support\Security\VisibilityScopeGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Le controle de demarrage qui refuse une portee globale manquante.
 *
 * Ces tests manipulent le registre de portees d'Eloquent plutot que le fichier
 * du modele : c'est le seul moyen de reproduire, dans un test, l'etat qu'aurait
 * l'application si l'attribut avait ete supprime.
 *
 * Le registre est remis en etat en fin de test par PRECAUTION, et non par
 * necessite : verifie, DatabaseServiceProvider::boot() appelle
 * Model::clearBootedModels() a chaque demarrage d'application, donc a chaque
 * test, et l'attribut #[ScopedBy] est relu. La suite entiere reste verte meme
 * sans ce tearDown — essaye. Il est conserve parce qu'une suite qui
 * s'executerait sans portee serait verte pour la pire des raisons, et que
 * cette garantie ne doit pas dependre d'un detail d'implementation du cadre.
 */
class VisibilityScopeGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        ReissuanceRequest::addGlobalScope(new RequestVisibilityScope);

        parent::tearDown();
    }

    #[Test]
    public function le_controle_passe_sur_l_application_telle_qu_elle_est_livree(): void
    {
        VisibilityScopeGuard::assertRegistered();

        $this->assertContains(
            RequestVisibilityScope::class,
            array_keys((new ReissuanceRequest)->getGlobalScopes()),
        );
    }

    /** La forme la plus banale : la ligne `#[ScopedBy(...)]` disparait. */
    #[Test]
    public function le_controle_refuse_une_portee_supprimee(): void
    {
        $this->retirerLaPortee();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Portée globale manquante/');

        VisibilityScopeGuard::assertRegistered();
    }

    /**
     * La seconde forme silencieuse : une portee valide, mais pas la bonne.
     *
     * Le modele fonctionne, aucune erreur n'est levee, et la restriction de
     * perimetre a disparu.
     */
    #[Test]
    public function le_controle_refuse_une_portee_remplacee_par_une_autre(): void
    {
        $this->retirerLaPortee();

        ReissuanceRequest::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                // Ne restreint rien : c'est tout le probleme.
            }
        });

        $this->expectException(RuntimeException::class);
        VisibilityScopeGuard::assertRegistered();
    }

    /** Le message doit dire quoi vérifier, pas seulement qu'il y a un problème. */
    #[Test]
    public function le_message_nomme_le_modele_la_portee_et_la_consequence(): void
    {
        $this->retirerLaPortee();

        try {
            VisibilityScopeGuard::assertRegistered();
            $this->fail('Le contrôle aurait dû lever une exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('RequestVisibilityScope', $e->getMessage());
            $this->assertStringContainsString('ReissuanceRequest', $e->getMessage());
            $this->assertStringContainsString('ScopedBy', $e->getMessage());
            $this->assertStringContainsString('tous les centres', mb_strtolower($e->getMessage()));
        }
    }

    /** Retire la portee du registre, comme le ferait la perte de l'attribut. */
    private function retirerLaPortee(): void
    {
        $registre = new \ReflectionProperty(Model::class, 'globalScopes');
        $portees = $registre->getValue();

        unset($portees[ReissuanceRequest::class][RequestVisibilityScope::class]);

        $registre->setValue(null, $portees);

        $this->assertNotContains(
            RequestVisibilityScope::class,
            array_keys((new ReissuanceRequest)->getGlobalScopes()),
            'Le retrait de la portée a échoué : le test ne prouverait rien.'
        );
    }
}
