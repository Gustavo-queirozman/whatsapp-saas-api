<?php

namespace App\Services\Dashboard;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DashboardOverviewService
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(): array
    {
        $companyId = $this->resolveCompanyId();
        $startOfDay = CarbonImmutable::now()->startOfDay();
        $endOfDay = $startOfDay->addDay();
        $statusCounts = $this->statusCounts($companyId);
        $todayConversationIds = $this->conversationIdsCreatedToday($companyId, $startOfDay, $endOfDay);
        $averageFirstResponseTime = $this->averageFirstResponseTime($companyId, $todayConversationIds);

        return [
            'conversations_today' => count($todayConversationIds),
            'messages_today' => $this->messagesTodayCount($companyId, $startOfDay, $endOfDay),
            'open_conversations' => $statusCounts[Conversation::STATUS_OPEN] ?? 0,
            'waiting_conversations' => $statusCounts[Conversation::STATUS_WAITING] ?? 0,
            'closed_conversations' => $statusCounts[Conversation::STATUS_CLOSED] ?? 0,
            'average_first_response_time' => $averageFirstResponseTime,
            'conversations_by_sector' => $this->conversationsBySector($companyId),
            'conversations_by_attendant' => $this->conversationsByAttendant($companyId),
            'connected_numbers' => $this->connectedNumbersCount($companyId),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $companyId): array
    {
        /** @var Collection<int, object> $rows */
        $rows = DB::table('conversations')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->where('company_id', $companyId)
            ->groupBy('status')
            ->get();

        return [
            Conversation::STATUS_OPEN => (int) ($rows->firstWhere('status', Conversation::STATUS_OPEN)?->total ?? 0),
            Conversation::STATUS_WAITING => (int) ($rows->firstWhere('status', Conversation::STATUS_WAITING)?->total ?? 0),
            Conversation::STATUS_CLOSED => (int) ($rows->firstWhere('status', Conversation::STATUS_CLOSED)?->total ?? 0),
        ];
    }

    /**
     * @return list<int>
     */
    private function conversationIdsCreatedToday(
        int $companyId,
        CarbonImmutable $startOfDay,
        CarbonImmutable $endOfDay
    ): array {
        return DB::table('conversations')
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<', $endOfDay)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function messagesTodayCount(
        int $companyId,
        CarbonImmutable $startOfDay,
        CarbonImmutable $endOfDay
    ): int {
        return (int) DB::table('messages')
            ->where('company_id', $companyId)
            ->whereRaw(
                'COALESCE(sent_at, created_at) >= ? AND COALESCE(sent_at, created_at) < ?',
                [$startOfDay->toDateTimeString(), $endOfDay->toDateTimeString()]
            )
            ->count();
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array{
     *     seconds: ?int,
     *     formatted: ?string,
     *     conversations_count: int
     * }
     */
    private function averageFirstResponseTime(int $companyId, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [
                'seconds' => null,
                'formatted' => null,
                'conversations_count' => 0,
            ];
        }

        /** @var Collection<int, object> $messages */
        $messages = DB::table('messages')
            ->select([
                'conversation_id',
                'direction',
                DB::raw('COALESCE(sent_at, created_at) as message_at'),
            ])
            ->where('company_id', $companyId)
            ->whereIn('conversation_id', $conversationIds)
            ->orderBy('conversation_id')
            ->orderByRaw('COALESCE(sent_at, created_at)')
            ->orderBy('id')
            ->get();

        $durationsInSeconds = [];

        foreach ($messages->groupBy('conversation_id') as $conversationMessages) {
            $firstInboundAt = null;

            foreach ($conversationMessages as $message) {
                $messageAt = CarbonImmutable::parse((string) $message->message_at);

                if ($message->direction === Message::DIRECTION_INBOUND && $firstInboundAt === null) {
                    $firstInboundAt = $messageAt;

                    continue;
                }

                if (
                    $message->direction === Message::DIRECTION_OUTBOUND
                    && $firstInboundAt !== null
                    && $messageAt->greaterThanOrEqualTo($firstInboundAt)
                ) {
                    $durationsInSeconds[] = $firstInboundAt->diffInSeconds($messageAt);

                    break;
                }
            }
        }

        if ($durationsInSeconds === []) {
            return [
                'seconds' => null,
                'formatted' => null,
                'conversations_count' => 0,
            ];
        }

        $averageInSeconds = (int) round(array_sum($durationsInSeconds) / count($durationsInSeconds));

        return [
            'seconds' => $averageInSeconds,
            'formatted' => $this->formatDuration($averageInSeconds),
            'conversations_count' => count($durationsInSeconds),
        ];
    }

    /**
     * @return list<array{
     *     sector_id: int,
     *     sector_name: string,
     *     sector_slug: string,
     *     total_conversations: int,
     *     open_conversations: int,
     *     waiting_conversations: int,
     *     closed_conversations: int
     * }>
     */
    private function conversationsBySector(int $companyId): array
    {
        /** @var Collection<int, object> $rows */
        $rows = DB::table('conversations')
            ->join('sectors', function ($join) use ($companyId): void {
                $join->on('sectors.id', '=', 'conversations.sector_id')
                    ->where('sectors.company_id', '=', $companyId);
            })
            ->select([
                'sectors.id as sector_id',
                'sectors.name as sector_name',
                'sectors.slug as sector_slug',
                DB::raw('COUNT(conversations.id) as total_conversations'),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as open_conversations",
                    Conversation::STATUS_OPEN
                )),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as waiting_conversations",
                    Conversation::STATUS_WAITING
                )),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as closed_conversations",
                    Conversation::STATUS_CLOSED
                )),
            ])
            ->where('conversations.company_id', $companyId)
            ->groupBy('sectors.id', 'sectors.name', 'sectors.slug')
            ->orderByDesc('total_conversations')
            ->orderBy('sectors.name')
            ->get();

        return $rows->map(static function (object $row): array {
            return [
                'sector_id' => (int) $row->sector_id,
                'sector_name' => (string) $row->sector_name,
                'sector_slug' => (string) $row->sector_slug,
                'total_conversations' => (int) $row->total_conversations,
                'open_conversations' => (int) $row->open_conversations,
                'waiting_conversations' => (int) $row->waiting_conversations,
                'closed_conversations' => (int) $row->closed_conversations,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     user_name: string,
     *     total_conversations: int,
     *     open_conversations: int,
     *     waiting_conversations: int,
     *     closed_conversations: int
     * }>
     */
    private function conversationsByAttendant(int $companyId): array
    {
        /** @var Collection<int, object> $rows */
        $rows = DB::table('conversations')
            ->join('company_users', function ($join) use ($companyId): void {
                $join->on('company_users.user_id', '=', 'conversations.assigned_user_id')
                    ->where('company_users.company_id', '=', $companyId);
            })
            ->join('users', 'users.id', '=', 'company_users.user_id')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(conversations.id) as total_conversations'),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as open_conversations",
                    Conversation::STATUS_OPEN
                )),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as waiting_conversations",
                    Conversation::STATUS_WAITING
                )),
                DB::raw(sprintf(
                    "SUM(CASE WHEN conversations.status = '%s' THEN 1 ELSE 0 END) as closed_conversations",
                    Conversation::STATUS_CLOSED
                )),
            ])
            ->where('conversations.company_id', $companyId)
            ->whereNotNull('conversations.assigned_user_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_conversations')
            ->orderBy('users.name')
            ->get();

        return $rows->map(static function (object $row): array {
            return [
                'user_id' => (int) $row->user_id,
                'user_name' => (string) $row->user_name,
                'total_conversations' => (int) $row->total_conversations,
                'open_conversations' => (int) $row->open_conversations,
                'waiting_conversations' => (int) $row->waiting_conversations,
                'closed_conversations' => (int) $row->closed_conversations,
            ];
        })->values()->all();
    }

    private function connectedNumbersCount(int $companyId): int
    {
        return (int) DB::table('whatsapp_instances')
            ->where('company_id', $companyId)
            ->where('status', 'connected')
            ->count();
    }

    private function resolveCompanyId(): int
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            throw new RuntimeException('Current company is not defined.');
        }

        return $companyId;
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
