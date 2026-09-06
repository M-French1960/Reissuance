<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevUiController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/sante', HealthController::class)->name('health');

/*
 * Toute route authentifiee passe par trois filtres :
 *   auth      — une session valide
 *   active    — le compte n'est ni suspendu ni desactive (R14)
 *   two-factor— les roles officiels ont une 2FA confirmee (4.1)
 *
 * Les routes d'authentification elles-memes sont enregistrees par Fortify.
 */
Route::middleware(['auth', 'active'])->group(function (): void {
    // Accessible SANS le filtre two-factor : c'est ici qu'on la configure.
    Route::get('/double-authentification', TwoFactorSetupController::class)
        ->name('two-factor.setup');

    Route::middleware('two-factor')->group(function (): void {
        Route::get('/tableau-de-bord', DashboardController::class)->name('dashboard');

        /*
         * Portail administrateur.
         *
         * Gouvernance des comptes uniquement : aucune de ces routes ne donne
         * acces au contenu d'un dossier d'identite (4.2 du brief).
         */
        Route::middleware('role:admin')->prefix('administration')->name('admin.')->group(function (): void {
            Route::get('/comptes', [UserController::class, 'index'])->name('users.index');
            Route::get('/comptes/nouveau', [UserController::class, 'create'])->name('users.create');
            Route::post('/comptes', [UserController::class, 'store'])->name('users.store');
            Route::patch('/comptes/{user}/statut', [UserController::class, 'changeStatus'])->name('users.status');
            Route::post('/comptes/{user}/reinitialisation', [UserController::class, 'sendPasswordReset'])->name('users.reset');
            Route::patch('/comptes/{user}/rattachement', [UserController::class, 'reassign'])->name('users.reassign');

            Route::get('/journal', [AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});

/*
 * Galerie de composants : hors production, sans exception.
 */
if (! app()->environment('production')) {
    Route::get('/dev/ui', DevUiController::class)->name('dev.ui');
}
