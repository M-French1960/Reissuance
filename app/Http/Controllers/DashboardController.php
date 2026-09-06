<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Aiguillage vers le tableau de bord du role.
 *
 * Les comptages passent par ReissuanceRequest, donc par la portee globale :
 * un officier ne peut pas compter les demandes d'un autre centre, meme si
 * cette requete-ci oubliait un where.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Citizen => view('dashboard.citizen', [
                'requests' => ReissuanceRequest::query()->latest('id')->limit(10)->get(),
            ]),

            UserRole::Officer => view('dashboard.officer', [
                'counts' => $this->countsByStatus(),
            ]),

            UserRole::Mayor => view('dashboard.mayor', [
                'counts' => $this->countsByStatus(),
            ]),

            UserRole::Admin => view('dashboard.admin', [
                'accounts' => User::query()
                    ->selectRaw('role, status, count(*) as total')
                    ->groupBy('role', 'status')
                    ->get(),
            ]),
        };
    }

    /** @return array<string, int> */
    private function countsByStatus(): array
    {
        $counts = ReissuanceRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];

        foreach (RequestStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }
}
