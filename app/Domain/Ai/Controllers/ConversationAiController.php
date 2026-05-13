<?php

namespace App\Domain\Ai\Controllers;

use App\Domain\Ai\Actions\GenerateConversationSummaryAction;
use App\Domain\Ai\Actions\SuggestConversationReplyAction;
use App\Domain\Conversations\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationAiController extends Controller
{
    public function summary(
        Request $request,
        Conversation $conversation,
        GenerateConversationSummaryAction $action
    ): JsonResponse {
        $this->authorize('view', $conversation);

        $result = $action->execute($conversation, $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $result->content,
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

    public function suggestReply(
        Request $request,
        Conversation $conversation,
        SuggestConversationReplyAction $action
    ): JsonResponse {
        $this->authorize('view', $conversation);

        $result = $action->execute($conversation, $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_reply' => $result->content,
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
