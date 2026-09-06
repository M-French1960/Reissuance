<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Chaque test s'execute dans une transaction annulee a la fin.
     *
     * On n'utilise PAS RefreshDatabase : les migrations doivent tourner sous le
     * role proprietaire, alors que les tests doivent s'executer sous le role
     * applicatif — c'est precisement ce qui permet de verifier que le journal
     * d'audit lui est inalterable.
     */
    use DatabaseTransactions;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh', [
                '--database' => 'pgsql_owner',
                '--force' => true,
            ])->run();

            self::$migrated = true;
        }
    }
}
