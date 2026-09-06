<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultation du journal d'audit par l'administrateur.
 *
 * METADONNEES SEULEMENT. L'administrateur voit qui a consulte quel dossier et
 * quand ; il ne voit pas ce que contenait le dossier. Il peut donc reperer un
 * agent qui consulte un nombre anormal de dossiers, sans acceder lui-meme aux
 * donnees. C'est le point d'equilibre vise par le 4.2 du brief.
 */
class AuditLogController extends Controller
{
    /**
     * Colonnes exposees. La liste est fermee : ajouter une colonne portant du
     * contenu de dossier romprait la separation ci-dessus.
     *
     * @var list<string>
     */
    private const SAFE_COLUMNS = [
        'id', 'actor_id', 'actor_role', 'action',
        'auditable_type', 'auditable_id',
        'from_status', 'to_status', 'ip_address', 'created_at',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAuditTrail', User::class);

        $logs = AuditLog::query()
            ->select(self::SAFE_COLUMNS)
            ->with('actor:id,name,email,role')
            ->when($request->filled('acteur'), fn ($q) => $q->where('actor_id', $request->integer('acteur')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'ilike', '%'.$request->input('action').'%'))
            ->when($request->filled('depuis'), fn ($q) => $q->where('created_at', '>=', $request->date('depuis')))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', ['logs' => $logs]);
    }
}
