<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {
    }

    public function __invoke(): Response
    {
        $user = request()->user();

        return Inertia::render('Dashboard', [
            'dashboard' => $this->dashboard->build($user),
        ]);
    }
}
