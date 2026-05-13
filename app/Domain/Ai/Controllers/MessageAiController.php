<?php

namespace App\Domain\Ai\Controllers;

use App\Domain\Ai\Actions\ClassifyMessageIntentAction;
use App\Domain\Conversations\Models\Message;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageAiController extends Controller
{
    public function classifyIntent(
        Request $request,
        Message $message,
        ClassifyMessageIntentAction $action
    ): JsonResponse {
        $this->authorize('view', $message);

        $result = $action->execute($message, $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'intent' => $result->intent,
                'usage' => [
                    'id' => $result->usageId,
                    'provider' => $result->provider,
                    'model' => $result->model,
                    'prompt_tokens' => $result->promptTokens,
                    'completion_tokens' => $result->completionTokens,
                    'total_tokens' => $result->totalTokens,
                ],
            ],
        ]);
    }
}
