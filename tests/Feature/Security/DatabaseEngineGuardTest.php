<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Support\Security\DatabaseEngineGuard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * MySQL est le seul moteur, et rien ne doit pouvoir en sortir en silence.
 *
 * Toutes les barrieres anti-fraude de PHOENIX sont posees dans la base. Sur un
 * autre moteur l'application demarrerait, les ecrans fonctionneraient, et une
 * transition interdite serait acceptee sans que rien ne le signale.
 */
class DatabaseEngineGuardTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $configurationInitiale = [];

    private string $defautInitial = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Les tests ci-dessous deregle volontairement la configuration de base
        // de donnees. Sans remise en etat, DatabaseTransactions echouerait a
        // annuler sa transaction sur une connexion devenue inexistante — et
        // l'erreur tomberait sur le test SUIVANT, ce qui est le pire endroit
        // pour chercher.
        $this->configurationInitiale = (array) Config::get('database.connections');
        $this->defautInitial = (string) Config::get('database.default');
    }

    protected function tearDown(): void
    {
        Config::set('database.connections', $this->configurationInitiale);
        Config::set('database.default', $this->defautInitial);

        parent::tearDown();
    }

    #[Test]
    public function l_application_tourne_bien_sur_mysql(): void
    {
        $this->assertSame(DatabaseEngineGuard::DRIVER, DB::connection()->getDriverName());
    }

    /**
     * Les connexions du cadre ne doivent plus etre la.
     *
     * Les retirer de config/database.php ne suffit pas : Laravel fusionne sa
     * configuration de base, et `connections` fait partie de ses options
     * fusionnables. Sans l'elagage du garde-fou, `sqlite`, `pgsql`, `mariadb`
     * et `sqlsrv` seraient toutes selectionnables par une simple variable
     * d'environnement.
     */
    #[Test]
    public function les_connexions_etrangeres_ont_ete_elaguees(): void
    {
        $declarees = array_keys((array) Config::get('database.connections'));

        sort($declarees);
        $attendues = DatabaseEngineGuard::CONNECTIONS;
        sort($attendues);

        $this->assertSame(
            $attendues,
            $declarees,
            'Seules les connexions du projet doivent subsister après le démarrage.'
        );
    }

    #[Test]
    public function toutes_les_connexions_restantes_sont_en_mysql(): void
    {
        foreach ((array) Config::get('database.connections') as $nom => $reglages) {
            $this->assertSame(
                DatabaseEngineGuard::DRIVER,
                $reglages['driver'] ?? null,
                "La connexion {$nom} doit utiliser MySQL."
            );
        }
    }

    #[Test]
    public function le_garde_fou_refuse_une_connexion_par_defaut_etrangere(): void
    {
        Config::set('database.default', 'sqlite');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Connexion par défaut inattendue/');

        DatabaseEngineGuard::enforce();
    }

    #[Test]
    public function le_garde_fou_refuse_un_pilote_etranger_sur_une_connexion_du_projet(): void
    {
        Config::set('database.connections.mysql.driver', 'sqlite');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/utilise le pilote/');

        DatabaseEngineGuard::enforce();
    }

    /** Le message doit dire quoi corriger, pas seulement qu'il y a un problème. */
    #[Test]
    public function le_message_nomme_la_variable_a_corriger_et_la_consequence(): void
    {
        Config::set('database.default', 'pgsql');

        try {
            DatabaseEngineGuard::enforce();
            $this->fail('Le garde-fou aurait dû lever une exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('DB_CONNECTION', $e->getMessage());
            $this->assertStringContainsString('pgsql', $e->getMessage());
            $this->assertStringContainsString("journal d'audit", $e->getMessage());
        }
    }

    /**
     * L'elagage ne doit pas emporter les connexions creees apres le demarrage.
     *
     * `phoenix:verifier-restauration` et `phoenix:droits` en fabriquent une a
     * la volee pour viser une autre base. Elles derivent de `mysql_owner`,
     * donc restent en MySQL — mais elles ne doivent pas faire echouer le
     * garde-fou s'il est rejoue.
     */
    #[Test]
    public function une_connexion_derivee_creee_a_la_volee_reste_acceptable(): void
    {
        Config::set('database.connections.verification', array_merge(
            (array) Config::get('database.connections.mysql_owner'),
            ['database' => 'phoenix_essai'],
        ));

        $this->assertSame(
            DatabaseEngineGuard::DRIVER,
            Config::get('database.connections.verification.driver'),
        );
    }
}
