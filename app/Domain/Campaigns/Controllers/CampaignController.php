<?php

namespace App\Domain\Campaigns\Controllers;

use App\Domain\Campaigns\Actions\CreateCampaignAction;
use App\Domain\Campaigns\Actions\DeleteCampaignAction;
use App\Domain\Campaigns\Actions\ImportCampaignContactsAction;
use App\Domain\Campaigns\Actions\PauseCampaignAction;
use App\Domain\Campaigns\Actions\ResumeCampaignAction;
use App\Domain\Campaigns\Actions\ScheduleCampaignAction;
use App\Domain\Campaigns\Actions\UpdateCampaignAction;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Requests\ImportCampaignContactsRequest;
use App\Domain\Campaigns\Requests\ScheduleCampaignRequest;
use App\Domain\Campaigns\Requests\StoreCampaignRequest;
use App\Domain\Campaigns\Requests\UpdateCampaignRequest;
use App\Domain\Campaigns\Resources\CampaignContactResource;
use App\Domain\Campaigns\Resources\CampaignResource;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $query = Campaign::query()
            ->withSummary()
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->where('name', 'like', "%{$search}%");
        }

        $campaigns = $query->get();

        return response()->json([
            'success' => true,
            'data' => CampaignResource::collection($campaigns)->resolve($request),
        ]);
    }

    public function store(StoreCampaignRequest $request, CreateCampaignAction $action): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $campaign = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $campaign->loadMissing('whatsappInstance')
            ->loadCount(Campaign::summaryCounts());

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function update(
        UpdateCampaignRequest $request,
        Campaign $campaign,
        UpdateCampaignAction $action
    ): JsonResponse {
        $this->authorize('update', $campaign);

        $campaign = $action->execute($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function destroy(Campaign $campaign, DeleteCampaignAction $action): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $action->execute($campaign);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Campanha removida com sucesso.',
            ],
        ]);
    }

    public function importContacts(
        ImportCampaignContactsRequest $request,
        Campaign $campaign,
        ImportCampaignContactsAction $action
    ): JsonResponse {
        $this->authorize('update', $campaign);

        $contacts = $action->execute($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'data' => CampaignContactResource::collection($contacts)->resolve($request),
        ]);
    }

    public function schedule(
        ScheduleCampaignRequest $request,
        Campaign $campaign,
        ScheduleCampaignAction $action
    ): JsonResponse {
        $this->authorize('update', $campaign);

        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::parse((string) $request->input('scheduled_at'))
            : null;

        $campaign = $action->execute($campaign, $scheduledAt);

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function pause(Request $request, Campaign $campaign, PauseCampaignAction $action): JsonResponse
    {
        $this->authorize('update', $campaign);

        $campaign = $action->execute($campaign);

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }

    public function resume(Request $request, Campaign $campaign, ResumeCampaignAction $action): JsonResponse
    {
        $this->authorize('update', $campaign);

        $campaign = $action->execute($campaign);

        return response()->json([
            'success' => true,
            'data' => (new CampaignResource($campaign))->resolve($request),
        ]);
    }
}
