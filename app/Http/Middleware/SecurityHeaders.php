<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite (4.5 du brief).
 *
 * La CSP est stricte : ni 'unsafe-inline', ni 'unsafe-eval'. C'est D-010 qui
 * l'a rendue atteignable — sans Livewire ni Alpine, plus rien n'evalue
 * d'expression a l'execution. Le prototype comptait 12 attributs onclick en
 * ligne, aucun n'a ete porte, et un test verifie qu'il n'en revient pas.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            // Aucun script tiers, aucun script en ligne. Le peu de JavaScript
            // du projet vit dans des fichiers servis depuis la meme origine.
            "script-src 'self'",
            // ATTENTION, ET LE COMMENTAIRE PRECEDENT DISAIT LE CONTRAIRE :
            // 'self' ne couvre PAS les attributs style=. Sans 'unsafe-inline',
            // le navigateur les refuse, silencieusement pour la page — seule
            // la console le dit. Verifie en construisant un graphique dont
            // les hauteurs etaient ecrites en style= : la page rendait 200 et
            // toutes les barres mesuraient 3 pixels (D-084).
            //
            // Une geometrie se pose donc en attributs SVG (x, y, width,
            // height, rx), qui ne sont pas des styles et que la politique ne
            // touche pas. Un test refuse tout style= sur cet ecran.
            "style-src 'self'",
            "img-src 'self' data: blob:",
            // Aucune police distante : la pile systeme est utilisee.
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            // La capture photo (jalon 3) a besoin de la camera, et de rien
            // d'autre. Tout le reste est refuse explicitement.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // HSTS n'a de sens qu'en TLS. L'imposer en developpement local
        // rendrait le site inaccessible en http sur la meme machine.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
