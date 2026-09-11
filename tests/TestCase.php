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
     *
     * La migration elle-meme a lieu dans tests/bootstrap.php, avant le premier
     * test : depuis setUp() elle arriverait trop tard, la transaction du trait
     * etant deja ouverte sous un compte encore sans droits.
     */
    use DatabaseTransactions;
}
