<?php

namespace App\Http\Controllers;

use App\Support\MonthCursor;
use App\Support\MonthlyReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $cursor = MonthCursor::fromRequest($request);

        return Inertia::render('Dashboard', [
            'report' => MonthlyReport::for($cursor->year(), $cursor->month())->toArray(),
        ]);
    }

}
