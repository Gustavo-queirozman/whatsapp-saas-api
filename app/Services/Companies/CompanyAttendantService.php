<?php

namespace App\Services\Companies;

use App\Domain\Companies\Models\CompanyUser;
use App\Domain\Conversations\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompanyAttendantService
{
    /**
     * @var array<int, string>
     */
    private const ATTENDANT_PERMISSIONS = [
        'conversations.view',
        'conversations.manage',
    ];

    /**
     * @return Collection<int, CompanyUser>
     */
    public function listByCompany(int $companyId): Collection
    {
        return $this->baseMembershipQuery($companyId)
            ->with('user')
            ->get()
            ->filter(fn (CompanyUser $membership): bool => $this->canHandleConversations($membership))
            ->sortBy(fn (CompanyUser $membership): string => mb_strtolower($membership->user?->name ?? ''))
            ->values();
    }

    /**
     * @return Collection<int, CompanyUser>
     */
    public function listBySector(int $companyId, int $sectorId): Collection
    {
        return $this->baseMembershipQuery($companyId)
            ->whereHas('user.sectors', function (Builder $query) use ($sectorId): void {
                $query->where('sectors.id', $sectorId);
            })
            ->with([
                'user' => function ($query) use ($companyId, $sectorId): void {
                    $query->withCount([
                        'assignedConversations as open_conversations_count' => function ($conversationQuery) use (
                            $companyId,
                            $sectorId
                        ): void {
                            $conversationQuery
                                ->where('company_id', $companyId)
                                ->where('sector_id', $sectorId)
                                ->where('status', Conversation::STATUS_OPEN);
                        },
                    ]);
                },
            ])
            ->get()
            ->filter(fn (CompanyUser $membership): bool => $this->canHandleConversations($membership))
            ->sortBy(function (CompanyUser $membership): string {
                $openConversationsCount = (int) ($membership->user?->open_conversations_count ?? PHP_INT_MAX);
                $userName = mb_strtolower($membership->user?->name ?? '');

                return str_pad((string) $openConversationsCount, 10, '0', STR_PAD_LEFT)
                    .'|'.$userName
                    .'|'.str_pad((string) $membership->user_id, 10, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    public function findByCompanyAndUser(int $companyId, int $userId): ?CompanyUser
    {
        $membership = $this->baseMembershipQuery($companyId)
            ->where('user_id', $userId)
            ->with('user')
            ->first();

        if (! $membership instanceof CompanyUser) {
            return null;
        }

        return $this->canHandleConversations($membership) ? $membership : null;
    }

    public function findBySectorAndUser(int $companyId, int $sectorId, int $userId): ?CompanyUser
    {
        $membership = $this->baseMembershipQuery($companyId)
            ->where('user_id', $userId)
            ->whereHas('user.sectors', function (Builder $query) use ($sectorId): void {
                $query->where('sectors.id', $sectorId);
            })
            ->with('user')
            ->first();

        if (! $membership instanceof CompanyUser) {
            return null;
        }

        return $this->canHandleConversations($membership) ? $membership : null;
    }

    private function canHandleConversations(CompanyUser $membership): bool
    {
        if ($membership->user === null || $membership->role === null) {
            return false;
        }

        return $membership->role->permissions
            ->pluck('slug')
            ->intersect(self::ATTENDANT_PERMISSIONS)
            ->isNotEmpty();
    }

    private function baseMembershipQuery(int $companyId): Builder
    {
        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('role.permissions');
    }
}
