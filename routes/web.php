<?php

declare(strict_types=1);

use App\Http\Controllers\DevUiController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/sante', HealthController::class)->name('health');

/*
 * Galerie de composants : hors production, sans exception.
 * Elle expose la structure interne de l'interface et n'a rien a faire sur un
 * service en ligne (8.3 du brief).
 */
if (! app()->environment('production')) {
    Route::get('/dev/ui', DevUiController::class)->name('dev.ui');
}
