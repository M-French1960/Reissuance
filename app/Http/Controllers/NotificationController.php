<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centre de notifications.
 *
 * C'est le canal dont on est sur qu'il arrive : il ne depend ni d'une adresse
 * valide, ni d'un serveur de courriel, ni du reseau du destinataire. Le
 * courriel vient en plus, jamais a la place.
 *
 * Chaque utilisateur ne voit que les siennes : la relation part de son propre
 * modele, aucune requete ne porte d'identifiant venu de la requete HTTP.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = $request->user();

        return view('notifications.index', [
            'notifications' => $utilisateur->notifications()->paginate(20),
            'nonLues' => $utilisateur->unreadNotifications()->count(),
        ]);
    }

    /** Marque tout comme lu. Aucun identifiant n'est accepte de la requete. */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Toutes vos notifications sont marquées comme lues.');
    }
}
