<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Chatbot\Models\BotFlowOption;
use App\Domain\Conversations\Actions\TransferConversationSectorAction;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Queues\Models\Sector;
use App\Services\Chatbot\BotFlowReplyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExecuteConversationBotFlowAction
{
    private const WEEK_DAY_MAP = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

    public function __construct(
        private readonly BotFlowReplyService $replyService,
        private readonly TransferConversationSectorAction $transferConversationSectorAction,
    ) {
    }

    public function execute(Conversation $conversation, Message $message): void
    {
        if ($conversation->assigned_user_id !== null) {
            return;
        }

        $botFlow = BotFlow::query()
            ->with(['options.targetSector'])
            ->where('sector_id', $conversation->sector_id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (! $botFlow instanceof BotFlow) {
            return;
        }

        if ($this->isOutsideOfficeHours($botFlow)) {
            $this->replyService->send(
                $conversation,
                $botFlow,
                (string) ($botFlow->out_of_hours_message ?? '')
            );

            return;
        }

        $matchedOption = $this->findMatchedOption($botFlow->options, $message->body);

        if ($matchedOption instanceof BotFlowOption) {
            $this->executeMatchedOption($conversation, $botFlow, $matchedOption);

            return;
        }

        if ($conversation->messages()->count() === 1) {
            $this->replyService->send($conversation, $botFlow, $this->composeMenuMessage($botFlow));

            return;
        }

        $this->replyService->send($conversation, $botFlow, $this->composeInvalidOptionMessage($botFlow));
    }

    private function executeMatchedOption(
        Conversation $conversation,
        BotFlow $botFlow,
        BotFlowOption $option
    ): void {
        if ($option->action === BotFlowOption::ACTION_TRANSFER_SECTOR && $option->targetSector instanceof Sector) {
            $conversation = $this->transferConversationSectorAction->execute($conversation, $option->targetSector);
        }

        if ($option->action === BotFlowOption::ACTION_OPEN_QUEUE) {
            $conversation->forceFill([
                'assigned_user_id' => null,
                'assigned_at' => null,
                'closed_at' => null,
                'status' => Conversation::STATUS_WAITING,
            ])->save();

            $conversation = $conversation->fresh(['contact', 'sector', 'whatsappInstance', 'assignedUser']);
        }

        $this->replyService->send(
            $conversation,
            $botFlow,
            (string) ($option->response_message ?? '')
        );
    }

    /**
     * @param  Collection<int, BotFlowOption>  $options
     */
    private function findMatchedOption(Collection $options, ?string $messageBody): ?BotFlowOption
    {
        $normalizedBody = $this->normalizeText($messageBody);

        if ($normalizedBody === null) {
            return null;
        }

        /** @var BotFlowOption|null $option */
        $option = $options
            ->filter(fn (BotFlowOption $item): bool => $item->is_active)
            ->first(function (BotFlowOption $item) use ($normalizedBody): bool {
                if ($item->number !== null && trim($item->number) !== '' && $normalizedBody === trim($item->number)) {
                    return true;
                }

                foreach ($item->keywords ?? [] as $keyword) {
                    $normalizedKeyword = $this->normalizeText(is_string($keyword) ? $keyword : null);

                    if ($normalizedKeyword !== null && str_contains($normalizedBody, $normalizedKeyword)) {
                        return true;
                    }
                }

                return false;
            });

        return $option;
    }

    private function composeMenuMessage(BotFlow $botFlow): string
    {
        $parts = array_filter([
            $botFlow->welcome_message,
            $botFlow->menu_message ?: $this->buildOptionsMenu($botFlow),
        ], fn (?string $value): bool => is_string($value) && trim($value) !== '');

        return implode("\n\n", $parts);
    }

    private function composeInvalidOptionMessage(BotFlow $botFlow): string
    {
        $parts = array_filter([
            $botFlow->invalid_option_message,
            $botFlow->menu_message ?: $this->buildOptionsMenu($botFlow),
        ], fn (?string $value): bool => is_string($value) && trim($value) !== '');

        return implode("\n\n", $parts);
    }

    private function buildOptionsMenu(BotFlow $botFlow): string
    {
        $lines = $botFlow->options
            ->filter(fn (BotFlowOption $option): bool => $option->is_active)
            ->map(function (BotFlowOption $option): string {
                $prefix = $option->number !== null && trim($option->number) !== ''
                    ? trim($option->number).'. '
                    : '- ';

                return $prefix.$option->label;
            })
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    private function isOutsideOfficeHours(BotFlow $botFlow): bool
    {
        if (! $botFlow->office_hours_enabled) {
            return false;
        }

        $timezone = $botFlow->office_hours_timezone ?: 'America/Sao_Paulo';
        $now = CarbonImmutable::now($timezone);
        $dayKey = self::WEEK_DAY_MAP[$now->dayOfWeekIso] ?? null;
        $schedule = is_string($dayKey) ? data_get($botFlow->office_hours, $dayKey) : null;

        if (! is_array($schedule) || ! ((bool) data_get($schedule, 'enabled', false))) {
            return true;
        }

        $start = data_get($schedule, 'start');
        $end = data_get($schedule, 'end');

        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return true;
        }

        $currentTime = $now->format('H:i');

        return $currentTime < $start || $currentTime > $end;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = Str::of($value)
            ->lower()
            ->ascii()
            ->trim()
            ->toString();

        return $normalizedValue === '' ? null : $normalizedValue;
    }
}
