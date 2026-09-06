<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route a un ou plusieurs roles.
 *
 * Double la Policy plutot que de la remplacer : le middleware ferme la route,
 * la Policy protege la ressource. Le 4.2 exige les deux.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $allowed = array_map(
            static fn (string $role): UserRole => UserRole::from($role),
            $roles
        );

        abort_unless($user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
