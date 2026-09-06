<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReissuanceRequest;
use Illuminate\View\View;

class DevUiController extends Controller
{
    public function __invoke(): View
    {
        return view('dev.ui', [
            'requests' => ReissuanceRequest::query()
                ->with('center')
                ->submitted()
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
