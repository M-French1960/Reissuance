<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ReissuanceRequest;
use App\Models\RequestMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * « Contact Officer » — le fil d'echanges d'un dossier.
 *
 * Hors des prefixes de role : le demandeur, l'officier et le maire ecrivent au
 * meme endroit. C'est la Policy qui decide, pas l'URL.
 */
class RequestMessageController extends Controller
{
    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('message', $reissuanceRequest);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'body.required' => 'Écrivez votre message avant de l\'envoyer.',
            'body.max' => 'Votre message est trop long : 2 000 caractères au maximum.',
        ]);

        $auteur = $request->user();

        DB::transaction(function () use ($reissuanceRequest, $auteur, $validated): void {
            $message = RequestMessage::create([
                'request_id' => $reissuanceRequest->id,
                'author_id' => $auteur->id,
                'author_role' => $auteur->role->value,
                'body' => trim($validated['body']),
            ]);

            AuditLog::create([
                'actor_id' => $auteur->id,
                'actor_role' => $auteur->role->value,
                'action' => 'request.message_sent',
                'auditable_type' => 'request_message',
                'auditable_id' => $message->id,
                // Le journal dit QU'UN message a ete envoye, jamais son
                // contenu : un echange sur un dossier contient des donnees
                // personnelles (garde-fou n6).
                'ip_address' => request()->ip(),
            ]);
        });

        $retour = $auteur->role === UserRole::Citizen
            ? route('citizen.requests.show', $reissuanceRequest)
            : url()->previous();

        return redirect($retour)->with('status', 'Votre message a été envoyé.');
    }
}
