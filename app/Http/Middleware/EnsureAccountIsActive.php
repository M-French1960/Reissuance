<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controle l'etat du compte a chaque requete.
 *
 * Deux roles :
 *
 * 1. Deconnecter immediatement un compte suspendu ou desactive. Sans cela, une
 *    suspension ne prendrait effet qu'a la prochaine connexion : un agent
 *    suspendu garderait sa session et continuerait a traiter des dossiers (R14).
 *
 * 2. Confiner un compte « pending » aux seules routes qui lui permettent de
 *    devenir utilisable : configuration de la 2FA et changement de mot de
 *    passe. Il ne voit aucun dossier tant qu'un administrateur ne l'a pas
 *    active.
 */
class EnsureAccountIsActive
{
    /**
     * Routes ouvertes a un compte en attente de configuration.
     *
     * @var list<string>
     */
    private const PENDING_ALLOWED_ROUTES = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.recovery-codes',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'user-password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->status === 'pending') {
            if (in_array($request->route()?->getName(), self::PENDING_ALLOWED_ROUTES, true)) {
                return $next($request);
            }

            return redirect()->route('two-factor.setup')->with(
                'status',
                "Votre compte n'est pas encore actif. Configurez votre double authentification, puis l'administration l'activera."
            );
        }

        if (! $user->isActive()) {
            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role->value,
                'action' => 'session.revoked_inactive_account',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'reason' => "Statut du compte : {$user->status}",
                'ip_address' => $request->ip(),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => "Votre compte n'est plus actif. Contactez l'administration de votre centre.",
            ]);
        }

        return $next($request);
    }
}
