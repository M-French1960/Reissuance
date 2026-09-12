<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActDraft;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service du PROJET d'acte.
 *
 * POURQUOI CET ECRAN EXISTE (D-068). D-064 a fait de l'officier le redacteur
 * de l'acte et du maire son signataire, et a scelle le lien par une empreinte
 * de contenu : le maire signe ce que l'officier a redige, ou rien. Mais aucune
 * route ne servait le projet. Le maire signait donc un document qu'il n'avait
 * jamais ouvert — l'empreinte prouvait que le contenu n'avait pas bouge, elle
 * ne prouvait pas qu'il avait ete lu.
 *
 * Trois regles, les memes que pour l'acte signe et les pieces d'identite :
 * hors de public/, Policy verifiee AVANT de servir le premier octet, et la
 * consultation journalisee — c'est elle qui permettra plus tard d'etablir que
 * le maire avait bien le projet sous les yeux.
 */
class ActDraftController extends Controller
{
    public function __invoke(Request $request, ActDraft $draft): StreamedResponse
    {
        $demande = ReissuanceRequest::loadForAuthorization($draft->request_id);

        // 404 et non 403 : les identifiants de projet sont sequentiels, et un
        // 403 apprendrait combien de dossiers ont ete instruits.
        abort_unless($request->user()?->can('viewDraft', $demande), 404);

        abort_if(
            $draft->document_path === null
            || ! Storage::disk('private')->exists($draft->document_path),
            404
        );

        $contenu = (string) Storage::disk('private')->get($draft->document_path);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role->value,
            'action' => 'act.draft_read',
            'auditable_type' => 'act_draft',
            'auditable_id' => $draft->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->stream(
            fn () => print ($contenu),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($contenu),
                // `inline` et non `attachment` : le maire doit LIRE ce projet
                // avant de decider, pas le ranger dans ses telechargements.
                'Content-Disposition' => 'inline; filename="projet-acte.pdf"',
                'Cache-Control' => 'no-store, private, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
