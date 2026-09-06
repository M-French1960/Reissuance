<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Citizen\AttachmentController;
use App\Http\Controllers\Citizen\ProfileController;
use App\Http\Controllers\Citizen\RequestTrackingController;
use App\Http\Controllers\Citizen\RequestWizardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevUiController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Officer\DecisionController;
use App\Http\Controllers\Officer\QueueController;
use App\Http\Controllers\Officer\VerificationController;
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
         * Parcours citoyen.
         *
         * Toutes ces routes sont protegees par la Policy autant que par le
         * middleware de role : le middleware ferme la route, la Policy protege
         * la ressource. Un citoyen n'atteint jamais la demande d'un autre.
         */
        Route::middleware('role:citizen')->prefix('mon-espace')->name('citizen.')->group(function (): void {
            Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');

            Route::get('/demandes', [RequestTrackingController::class, 'index'])->name('requests.index');
            Route::post('/demandes/nouvelle', [RequestWizardController::class, 'start'])->name('requests.start');
            Route::get('/demandes/{reissuanceRequest}/etape/{step}', [RequestWizardController::class, 'show'])
                ->whereNumber('step')->name('requests.step');
            Route::post('/demandes/{reissuanceRequest}/etape/{step}', [RequestWizardController::class, 'save'])
                ->whereNumber('step')->name('requests.save');
            Route::post('/demandes/{reissuanceRequest}/pieces', [AttachmentController::class, 'store'])
                ->name('requests.attachments.store');
            Route::get('/demandes/{reissuanceRequest}', [RequestTrackingController::class, 'show'])->name('requests.show');
        });

        /*
         * Parcours officier.
         *
         * La portee globale restreint deja aux demandes du centre de
         * l'officier : aucune de ces routes n'a de `where` de securite a
         * poser, et un oubli ne peut pas faire fuir hors perimetre.
         */
        Route::middleware('role:officer')->prefix('verification')->name('officer.')->group(function (): void {
            Route::get('/file', QueueController::class)->name('queue');
            Route::post('/demandes/{reissuanceRequest}/prise-en-charge', [VerificationController::class, 'claim'])
                ->name('verification.claim');
            Route::get('/demandes/{reissuanceRequest}/etape/{step}', [VerificationController::class, 'show'])
                ->whereNumber('step')->name('verification.step');
            Route::post('/demandes/{reissuanceRequest}/etape/{step}/constat', [VerificationController::class, 'acknowledge'])
                ->whereNumber('step')->name('verification.acknowledge');
            Route::post('/demandes/{reissuanceRequest}/controle-identite', [VerificationController::class, 'runIdentityCheck'])
                ->name('verification.identity');
            Route::post('/demandes/{reissuanceRequest}/recherche-registre', [VerificationController::class, 'runRegistrySearch'])
                ->name('verification.registry');
            Route::post('/demandes/{reissuanceRequest}/decision', [DecisionController::class, 'store'])
                ->name('decision.store');
        });

        /*
         * Service des pieces d'identite.
         *
         * Hors du prefixe citoyen : l'officier et le maire doivent aussi
         * pouvoir consulter les pieces des dossiers de leur perimetre. La
         * Policy viewIdentityDocuments decide, et exclut l'administrateur.
         */
        Route::get('/pieces/{attachment}', [AttachmentController::class, 'show'])
            ->name('citizen.attachments.show');

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
