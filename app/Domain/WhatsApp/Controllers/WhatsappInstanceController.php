<?php

namespace App\Domain\WhatsApp\Controllers;

use App\Domain\WhatsApp\Actions\CreateWhatsappInstanceAction;
use App\Domain\WhatsApp\Actions\DeleteWhatsappInstanceAction;
use App\Domain\WhatsApp\Actions\DisconnectWhatsappInstanceAction;
use App\Domain\WhatsApp\Actions\GetWhatsappInstanceQrCodeAction;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Domain\WhatsApp\Requests\StoreWhatsappInstanceRequest;
use App\Domain\WhatsApp\Resources\WhatsappInstanceResource;
use App\DTOs\WhatsApp\CreateWhatsappInstanceData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappInstanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WhatsappInstance::class);

        $query = WhatsappInstance::query()
            ->with('sector')
            ->orderBy('instance_name');

        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->integer('sector_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        $instances = $query->get();

        return response()->json([
            'success' => true,
            'data' => WhatsappInstanceResource::collection($instances)->resolve($request),
        ]);
    }

    public function store(
        StoreWhatsappInstanceRequest $request,
        CreateWhatsappInstanceAction $action
    ): JsonResponse {
        $this->authorize('create', WhatsappInstance::class);

        $instance = $action->execute(CreateWhatsappInstanceData::fromRequest($request));
        $instance->load('sector');

        return response()->json([
            'success' => true,
            'data' => (new WhatsappInstanceResource($instance))->resolve($request),
        ], 201);
    }

    public function show(Request $request, WhatsappInstance $whatsappInstance): JsonResponse
    {
        $this->authorize('view', $whatsappInstance);
        $whatsappInstance->load('sector');

        return response()->json([
            'success' => true,
            'data' => (new WhatsappInstanceResource($whatsappInstance))->resolve($request),
        ]);
    }

    public function qrcode(
        Request $request,
        WhatsappInstance $whatsappInstance,
        GetWhatsappInstanceQrCodeAction $action
    ): JsonResponse {
        $this->authorize('view', $whatsappInstance);

        $result = $action->execute($whatsappInstance);
        $result['instance']->load('sector');

        return response()->json([
            'success' => true,
            'data' => [
                'instance' => (new WhatsappInstanceResource($result['instance']))->resolve($request),
                'qrcode' => $result['qrcode'],
                'status' => $result['status'],
            ],
        ]);
    }

    public function disconnect(
        Request $request,
        WhatsappInstance $whatsappInstance,
        DisconnectWhatsappInstanceAction $action
    ): JsonResponse {
        $this->authorize('update', $whatsappInstance);

        $instance = $action->execute($whatsappInstance);
        $instance->load('sector');

        return response()->json([
            'success' => true,
            'data' => (new WhatsappInstanceResource($instance))->resolve($request),
        ]);
    }

    public function destroy(WhatsappInstance $whatsappInstance, DeleteWhatsappInstanceAction $action): JsonResponse
    {
        $this->authorize('delete', $whatsappInstance);

        $action->execute($whatsappInstance);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Instancia removida com sucesso.',
            ],
        ]);
    }
}
