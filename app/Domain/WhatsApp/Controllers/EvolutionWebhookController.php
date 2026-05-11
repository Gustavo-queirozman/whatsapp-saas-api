<?php

namespace App\Domain\WhatsApp\Controllers;

use App\Domain\WhatsApp\Actions\ProcessEvolutionWebhookAction;
use App\DTOs\WhatsApp\EvolutionWebhookPayloadData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessEvolutionWebhookAction $action): JsonResponse
    {
        $result = $action->execute(EvolutionWebhookPayloadData::fromPayload($request->all()));

        return response()->json([
            'success' => $result->success,
            'data' => $result->success ? $result->toArray() : null,
            'message' => $result->success ? null : $result->message,
        ], $result->httpStatus);
    }
}
