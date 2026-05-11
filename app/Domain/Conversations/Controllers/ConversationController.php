<?php

namespace App\Domain\Conversations\Controllers;

use App\DTOs\Conversations\SendConversationMessageData;
use App\Domain\Conversations\Actions\CloseConversationAction;
use App\Domain\Conversations\Actions\ReopenConversationAction;
use App\Domain\Conversations\Actions\SendConversationMessageAction;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Conversations\Requests\ListConversationsRequest;
use App\Domain\Conversations\Requests\SendConversationMessageRequest;
use App\Domain\Conversations\Resources\ConversationResource;
use App\Domain\Conversations\Resources\MessageResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(ListConversationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $query = Conversation::query()
            ->with(['contact', 'sector', 'whatsappInstance', 'assignedUser'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->integer('sector_id'));
        }

        if ($request->filled('whatsapp_instance_id')) {
            $query->where('whatsapp_instance_id', $request->integer('whatsapp_instance_id'));
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->integer('assigned_user_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');

            $query->whereHas('contact', function ($contactQuery) use ($search): void {
                $contactQuery->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
        }

        $conversations = $query->get();

        return response()->json([
            'success' => true,
            'data' => ConversationResource::collection($conversations)->resolve($request),
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['contact', 'sector', 'whatsappInstance', 'assignedUser'])
            ->loadCount('messages');

        return response()->json([
            'success' => true,
            'data' => (new ConversationResource($conversation))->resolve($request),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->authorize('viewAny', Message::class);

        $messages = $conversation->messages()
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => MessageResource::collection($messages)->resolve($request),
        ]);
    }

    public function sendMessage(
        SendConversationMessageRequest $request,
        Conversation $conversation,
        SendConversationMessageAction $action
    ): JsonResponse {
        $this->authorize('update', $conversation);
        $this->authorize('create', Message::class);

        $message = $action->execute(
            $conversation,
            SendConversationMessageData::fromRequest($request),
        );

        $conversation->refresh()->load(['contact', 'sector', 'whatsappInstance', 'assignedUser'])
            ->loadCount('messages');

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => (new ConversationResource($conversation))->resolve($request),
                'message' => (new MessageResource($message))->resolve($request),
            ],
        ], 201);
    }

    public function close(
        Request $request,
        Conversation $conversation,
        CloseConversationAction $action
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $conversation = $action->execute($conversation)->loadCount('messages');

        return response()->json([
            'success' => true,
            'data' => (new ConversationResource($conversation))->resolve($request),
        ]);
    }

    public function reopen(
        Request $request,
        Conversation $conversation,
        ReopenConversationAction $action
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $conversation = $action->execute($conversation)->loadCount('messages');

        return response()->json([
            'success' => true,
            'data' => (new ConversationResource($conversation))->resolve($request),
        ]);
    }
}
