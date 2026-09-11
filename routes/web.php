<?php

declare(strict_types=1);

use App\Http\Controllers\ActDocumentController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Citizen\AttachmentController;
use App\Http\Controllers\Citizen\CancellationController;
use App\Http\Controllers\Citizen\PaymentController;
use App\Http\Controllers\Citizen\ProfileController;
use App\Http\Controllers\Citizen\RequestTrackingController;
use App\Http\Controllers\Citizen\RequestWizardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevUiController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HrSkillsWebhookController;
use App\Http\Controllers\Mayor\DashboardController as MayorDashboardController;
use App\Http\Controllers\Mayor\DecisionController as MayorDecisionController;
use App\Http\Controllers\Mayor\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Officer\DecisionController;
use App\Http\Controllers\Officer\QueueController;
use App\Http\Controllers\Officer\VerificationController;
use App\Http\Controllers\RequestMessageController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/sante', HealthController::class)->name('health');

/*
 * Rappel de l'operateur de paiement.
 *
 * PUBLIQUE a dessein — c'est un serveur tiers qui appelle — mais gardee par la
 * signature HMAC du corps brut, verifiee avant toute lecture. Sans secret
 * configure, tout rappel est refuse.
 */
Route::post('/rappels/hrskills', HrSkillsWebhookController::class)->name('webhooks.hrskills');

/*
 * Toute route authentifiee passe par trois filtres :
 *   auth      — une session valide
 *   active    — le compte n'est ni suspendu ni desactive (R14)
 *   two-factor— les roles officiels ont une 2FA confirmee (4.1)
 *
 * `active` n'est PAS repete ici : il est pose sur le groupe `web` entier dans
 * bootstrap/app.php, pour couvrir aussi les routes que Fortify enregistre
 * lui-meme, qui lui echappaient. Le repeter le ferait s'executer deux fois et
 * journaliser deux fois la revocation d'une session.
 */
Route::middleware('auth')->group(function (): void {
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

            /*
             * Annulation par le demandeur — « Cancel Request » du diagramme.
             * Possible tant que personne n'a pris le dossier en charge.
             */
            Route::post('/demandes/{reissuanceRequest}/annulation', [CancellationController::class, 'store'])
                ->name('requests.cancel');

            /*
             * Reglement des frais.
             *
             * Un seul ecran sert les deux placements possibles de la barriere
             * de paiement (D-041) : seul le moment ou l'on y arrive change.
             */
            Route::get('/demandes/{reissuanceRequest}/paiement', [PaymentController::class, 'show'])
                ->name('requests.payment');
            Route::post('/demandes/{reissuanceRequest}/paiement', [PaymentController::class, 'store'])
                ->name('requests.payment.store');
            Route::post('/demandes/{reissuanceRequest}/paiement/rapprocher', [PaymentController::class, 'reconcile'])
                ->name('requests.payment.reconcile');
            Route::get('/demandes/{reissuanceRequest}/paiement/recu', [PaymentController::class, 'receipt'])
                ->name('requests.payment.receipt');
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
            Route::post('/demandes/{reissuanceRequest}/comparaison-faciale', [VerificationController::class, 'runFacialComparison'])
                ->name('verification.facial');
            Route::post('/demandes/{reissuanceRequest}/recherche-registre', [VerificationController::class, 'runRegistrySearch'])
                ->name('verification.registry');
            Route::post('/demandes/{reissuanceRequest}/decision', [DecisionController::class, 'store'])
                ->name('decision.store');
        });

        /*
         * Parcours maire.
         *
         * La portee globale restreint deja a sa commune ET aux deux etats ou
         * il a competence : une demande de sa commune en pending ou
         * under_review lui reste invisible (4.2 du brief).
         */
        Route::middleware('role:mayor')->prefix('signature')->name('mayor.')->group(function (): void {
            Route::get('/tableau-de-bord', MayorDashboardController::class)->name('dashboard');
            Route::get('/dossiers/{reissuanceRequest}', ReviewController::class)->name('review');
            Route::post('/dossiers/{reissuanceRequest}/signer', [MayorDecisionController::class, 'sign'])->name('sign');
            Route::post('/dossiers/{reissuanceRequest}/rejeter', [MayorDecisionController::class, 'reject'])->name('reject');
            Route::post('/dossiers/{reissuanceRequest}/retourner', [MayorDecisionController::class, 'returnToOfficer'])->name('return');
        });

        /*
         * Fil d'echanges d'un dossier — « Contact Officer ».
         *
         * Hors des prefixes de role, comme les actes et les pieces : c'est la
         * Policy qui decide qui ecrit, pas l'URL. L'administrateur en est
         * exclu (4.2).
         */
        Route::post('/dossiers/{reissuanceRequest}/messages', [RequestMessageController::class, 'store'])
            ->name('requests.messages.store');

        /*
         * Centre de notifications, commun aux quatre roles.
         *
         * Aucun identifiant n'est pris dans l'URL : la liste part du modele de
         * l'utilisateur connecte, il ne peut donc pas lire celles d'un autre.
         */
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/lues', [NotificationController::class, 'markAllRead'])->name('notifications.read');

        /*
         * Acte signe et preuve de signature.
         *
         * Hors du prefixe maire : le citoyen telecharge son acte, l'officier
         * et le maire le consultent dans leur perimetre. La Policy decide.
         */
        Route::get('/actes/{signature}', [ActDocumentController::class, 'document'])->name('acts.document');
        Route::get('/actes/{signature}/preuve', [ActDocumentController::class, 'proof'])->name('acts.proof');

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

            /*
             * Affectations bloquees (D-057). Ce n'est PAS un acces aux
             * dossiers : le controleur ne lit qu'une liste blanche de colonnes
             * sans donnee d'identite.
             */
            Route::get('/affectations', [AssignmentController::class, 'index'])->name('assignments.index');
            /*
             * L'identifiant est passe en clair, et NON resolu par le liaison
             * automatique de modele : celle-ci applique la portee globale, qui
             * rend l'administrateur aveugle a toute demande — elle renvoyait
             * donc 404. Le controleur resout via la methode auditee, qui ne
             * lit que des colonnes sans donnee d'identite.
             */
            Route::post('/affectations/{demande}/liberation', [AssignmentController::class, 'release'])
                ->whereNumber('demande')->name('assignments.release');

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
