<?php

namespace App\Domain\Dashboard\Controllers;

use App\Domain\Conversations\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardOverviewService;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(
        Request $request,
        CurrentCompany $currentCompany,
        DashboardOverviewService $dashboardOverviewService
    ): JsonResponse {
        $this->authorize('viewAny', Conversation::class);

        abort_if($currentCompany->id() === null, 403);

        return response()->json([
            'success' => true,
            'data' => $dashboardOverviewService->getOverview(),
        ]);
    }
}
