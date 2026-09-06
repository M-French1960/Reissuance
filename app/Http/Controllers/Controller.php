<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Depuis Laravel 11, le controleur de base n'inclut plus ce trait : sans
     * lui, tout appel a $this->authorize() leve une Error a l'execution. Les
     * Policies etaient donc contournees par les controleurs, seul le
     * middleware de role tenant encore. Rattrape par les tests du portail.
     */
    use AuthorizesRequests;
}
