<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * @param  array<int, string>  $permissions
 */
$authorizeCompanyChannel = static function (User $user, int $companyId, array $permissions): bool {
    $currentCompanyId = request()->attributes->get('current_company_id');

    if ($currentCompanyId === null && request()->hasHeader('X-Company-Id')) {
        $currentCompanyId = (int) request()->header('X-Company-Id');
    }

    if ((int) $currentCompanyId !== $companyId) {
        return false;
    }

    foreach ($permissions as $permission) {
        if ($user->hasCompanyPermission($permission, $companyId)) {
            return true;
        }
    }

    return false;
};

Broadcast::channel('companies.{companyId}.conversations', function (User $user, int $companyId) use ($authorizeCompanyChannel): bool {
    return $authorizeCompanyChannel($user, $companyId, [
        'conversations.view',
        'conversations.manage',
    ]);
}, ['guards' => ['sanctum']]);

Broadcast::channel('companies.{companyId}.conversations.{conversationId}', function (User $user, int $companyId, int $conversationId) use ($authorizeCompanyChannel): bool {
    return $authorizeCompanyChannel($user, $companyId, [
        'conversations.view',
        'conversations.manage',
    ]);
}, ['guards' => ['sanctum']]);

Broadcast::channel('companies.{companyId}.whatsapp.instances', function (User $user, int $companyId) use ($authorizeCompanyChannel): bool {
    return $authorizeCompanyChannel($user, $companyId, [
        'whatsapp.view',
        'whatsapp.manage',
    ]);
}, ['guards' => ['sanctum']]);

Broadcast::channel('companies.{companyId}.whatsapp.instances.{instanceId}', function (User $user, int $companyId, int $instanceId) use ($authorizeCompanyChannel): bool {
    return $authorizeCompanyChannel($user, $companyId, [
        'whatsapp.view',
        'whatsapp.manage',
    ]);
}, ['guards' => ['sanctum']]);

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->getKey() === $id;
});
