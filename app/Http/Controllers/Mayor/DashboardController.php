<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mayor;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord du maire : deux files (5.4 du brief).
 *
 * La portée globale restreint déjà à la commune du maire ET aux deux seuls
 * états où il a compétence. Une demande de sa commune en `pending` ou
 * `under_review` lui reste invisible (§4.2) : ce n'est pas un filtre
 * d'interface, c'est une portée appliquée en base.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', ReissuanceRequest::class);

        return view('mayor.dashboard', [
            'aSigner' => ReissuanceRequest::query()
                ->where('status', RequestStatus::AwaitingSignature->value)
                ->with('center:id,name')
                ->orderBy('submitted_at')
                ->paginate(15, ['*'], 'page_signature')
                ->withQueryString(),

            'escaladees' => ReissuanceRequest::query()
                ->where('status', RequestStatus::Escalated->value)
                ->with(['center:id,name', 'decisions' => fn ($q) => $q->latest('created_at')->limit(1)])
                ->orderBy('submitted_at')
                ->paginate(15, ['*'], 'page_escalade')
                ->withQueryString(),
        ]);
    }
}
