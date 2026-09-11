<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Policies\ReissuanceRequestPolicy;
use App\Policies\UserPolicy;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le statut du compte est une barriere d'autorisation, pas seulement de porte.
 *
 * LE TROU QUE CECI FERME. Aucune des capacites des Policies ne regardait le
 * statut du compte. `decide()` repondait **oui** pour un officier suspendu ;
 * seul le middleware `EnsureAccountIsActive` l'arretait, a la porte HTTP. Or
 * les Policies sont aussi consultees hors requete — file d'attente, commandes,
 * semences — et surtout : une Policy qu'on relit ne disait pas ce qu'elle
 * applique. Croire lire une barriere la ou elle n'est pas, c'est ce qui
 * produit la faille suivante (D-059).
 *
 * Ces tests parcourent la matrice entiere : chaque role, chaque statut, chaque
 * capacite. Une capacite ajoutee demain est couverte sans qu'on y pense, parce
 * que la liste est lue sur les Policies elles-memes.
 */
class AccountStatusAuthorizationTest extends TestCase
{
    private CivilStatusCenter $centre;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-600000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        app(RequestTransitionService::class)->transition(
            $this->demande->refresh(), RequestStatus::Pending, $citoyen
        );
    }

    /** Les statuts qui ne doivent rien autoriser. */
    public const INACTIFS = ['pending', 'suspended', 'disabled'];

    /**
     * Les capacites, lues sur les Policies : une capacite ajoutee demain est
     * couverte sans qu'on ait a penser a ce fichier.
     *
     * @return list<string>
     */
    private static function capacites(string $policy): array
    {
        $reflexion = new \ReflectionClass($policy);

        return array_values(array_diff(
            array_map(
                static fn (\ReflectionMethod $m): string => $m->getName(),
                $reflexion->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
            ['__construct', 'before', 'allow', 'deny'],
        ));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rolesInactifs(): iterable
    {
        foreach (['citizen', 'officer', 'mayor', 'admin'] as $role) {
            foreach (self::INACTIFS as $statut) {
                yield "{$role} / {$statut}" => [$role, $statut];
            }
        }
    }

    private function compte(string $role, string $statut): User
    {
        $fabrique = User::factory();

        $utilisateur = match ($role) {
            'officer' => $fabrique->officer($this->centre)->create(),
            'mayor' => $fabrique->mayor($this->centre->commune)->create(),
            'admin' => $fabrique->admin()->create(),
            default => $fabrique->citizen()->create(),
        };

        $utilisateur->forceFill(['status' => $statut])->save();

        return $utilisateur->refresh();
    }

    /**
     * Aucune capacite sur une demande, quel que soit le role, si le compte
     * n'est pas actif.
     */
    #[Test]
    #[DataProvider('rolesInactifs')]
    public function un_compte_inactif_n_a_aucune_capacite_sur_une_demande(string $role, string $statut): void
    {
        $utilisateur = $this->compte($role, $statut);

        foreach (self::capacites(ReissuanceRequestPolicy::class) as $capacite) {
            $this->assertFalse(
                $utilisateur->can($capacite, $this->demande),
                "{$role} au statut {$statut} ne doit pas pouvoir « {$capacite} »."
            );
        }
    }

    /** Idem sur la gouvernance des comptes. */
    #[Test]
    #[DataProvider('rolesInactifs')]
    public function un_compte_inactif_n_a_aucune_capacite_sur_les_comptes(string $role, string $statut): void
    {
        $utilisateur = $this->compte($role, $statut);
        $cible = User::factory()->officer($this->centre)->create();

        foreach (self::capacites(UserPolicy::class) as $capacite) {
            $this->assertFalse(
                $utilisateur->can($capacite, $capacite === 'viewAny' || $capacite === 'create' || $capacite === 'viewAuditTrail'
                    ? User::class
                    : $cible),
                "{$role} au statut {$statut} ne doit pas pouvoir « {$capacite} »."
            );
        }
    }

    /**
     * Le controle ne doit pas TOUT refuser : un compte actif garde ses droits.
     *
     * Sans cette contrepartie, le test precedent serait satisfait par une
     * regle qui refuse simplement tout le monde.
     */
    #[Test]
    public function un_compte_actif_conserve_ses_capacites(): void
    {
        $officier = $this->compte('officer', 'active');
        $admin = $this->compte('admin', 'active');

        $this->assertTrue($officier->can('view', $this->demande));
        $this->assertTrue($officier->can('claim', $this->demande));
        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('viewAuditTrail', User::class));
    }

    /**
     * Le cas qui a declenche ce jalon : la Policy elle-meme doit refuser.
     *
     * Avant D-059, `decide()` repondait oui pour un officier suspendu et seul
     * le middleware l'arretait.
     */
    #[Test]
    public function un_officier_suspendu_ne_peut_plus_decider_meme_sans_requete_http(): void
    {
        $officier = $this->compte('officer', 'active');

        $this->actingAs($officier)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertRedirect();

        $this->assertTrue($officier->fresh()->can('decide', $this->demande->refresh()));

        $officier->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse(
            $officier->fresh()->can('decide', $this->demande->refresh()),
            'La Policy doit refuser, sans dépendre du middleware HTTP.'
        );
    }

    /** Un administrateur suspendu ne peut plus réactiver qui que ce soit. */
    #[Test]
    public function un_administrateur_suspendu_ne_peut_plus_gouverner_les_comptes(): void
    {
        $admin = $this->compte('admin', 'suspended');
        $cible = User::factory()->officer($this->centre)->create();

        $this->assertFalse($admin->can('changeStatus', $cible));
        $this->assertFalse($admin->can('reassign', $cible));
        $this->assertFalse($admin->can('triggerPasswordReset', $cible));
    }
}
