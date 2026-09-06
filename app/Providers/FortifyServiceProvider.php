<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        $this->registerViews();
        $this->registerRateLimiters();
        $this->registerAuthentication();
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
    }

    /**
     * Limitation de debit et verrouillage progressif (4.1 du brief).
     *
     * La limite porte sur le couple (adresse, IP) : limiter par IP seule
     * bloquerait tout un centre partageant une connexion des qu'un agent se
     * trompe, et limiter par adresse seule laisserait un attaquant balayer
     * les comptes depuis une seule machine.
     */
    private function registerRateLimiters(): void
    {
        $max = (int) config('phoenix.security.login_max_attempts');

        RateLimiter::for('login', function (Request $request) use ($max) {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return [
                Limit::perMinute($max)->by($key),
                // Plafond par IP, plus large : freine un balayage de comptes
                // sans penaliser un poste partage.
                Limit::perMinute($max * 4)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute($max)
            ->by((string) $request->session()->get('login.id')));

        // La reinitialisation envoie un courriel : la brider evite d'en faire
        // un outil de harcelement ou d'enumeration de comptes.
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinutes(15, 3)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }

    /**
     * Authentification : verifie le mot de passe ET l'etat du compte.
     *
     * Un compte suspendu ou desactive ne doit pas pouvoir ouvrir de session,
     * meme avec le bon mot de passe. Le message reste identique dans tous les
     * cas d'echec : distinguer « mot de passe faux » de « compte suspendu »
     * revelerait l'existence du compte.
     */
    private function registerAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', $request->input('email'))->first();

            if ($user === null || ! app('hash')->check((string) $request->input('password'), $user->password)) {
                AuditLog::create([
                    'actor_id' => $user?->id,
                    'actor_role' => $user?->role->value,
                    'action' => 'auth.failed',
                    'auditable_type' => 'user',
                    'auditable_id' => $user?->id,
                    'ip_address' => $request->ip(),
                ]);

                return null;
            }

            // Un compte « pending » peut se connecter : c'est le seul moyen
            // pour lui de configurer sa 2FA. Le middleware le confine ensuite
            // aux routes de configuration.
            if (! $user->isActive() && ! $user->isPending()) {
                AuditLog::create([
                    'actor_id' => $user->id,
                    'actor_role' => $user->role->value,
                    'action' => 'auth.refused_inactive_account',
                    'auditable_type' => 'user',
                    'auditable_id' => $user->id,
                    'reason' => "Statut du compte : {$user->status}",
                    'ip_address' => $request->ip(),
                ]);

                return null;
            }

            return $user;
        });
    }
}
