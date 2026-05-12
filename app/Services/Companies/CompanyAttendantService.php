<?php

namespace App\Services\Companies;

use App\Domain\Companies\Models\CompanyUser;
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
        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['user', 'role.permissions'])
            ->get()
            ->filter(fn (CompanyUser $membership): bool => $this->canHandleConversations($membership))
            ->sortBy(fn (CompanyUser $membership): string => mb_strtolower($membership->user?->name ?? ''))
            ->values();
    }

    public function findByCompanyAndUser(int $companyId, int $userId): ?CompanyUser
    {
        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->with(['user', 'role.permissions'])
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
}
