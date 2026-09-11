<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reglages du systeme — consultation seule (D-060).
 *
 * Aucune methode d'ecriture, et c'est le coeur de la decision : basculer un
 * prestataire sur l'adaptateur factice depuis un navigateur reviendrait a
 * faire delivrer des actes sans verification reelle. Ces reglages restent des
 * variables d'environnement, sous le controle de qui deploie.
 *
 * La consultation est journalisee : cet ecran decrit la configuration du
 * systeme, ce qui est une information d'exploitation. Savoir qui l'a regardee,
 * et quand, coute une ligne.
 */
class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role->value,
            'action' => 'settings.viewed',
            // Ni type ni identifiant : cet evenement ne porte sur aucune
            // ligne. Les deux colonnes sont nullables, et inventer un « 0 »
            // ferait croire a une ligne qui n'existe pas.
            'ip_address' => $request->ip(),
        ]);

        return view('admin.settings.index', [
            'sections' => SystemSettings::sections(),
            'factices' => SystemSettings::fakeProviders(),
        ]);
    }
}
