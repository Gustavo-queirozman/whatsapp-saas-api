<?php

namespace App\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUser;
use App\Domain\Companies\Models\Role;
use App\Domain\Queues\Models\Sector;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot(['role_id', 'is_active'])
            ->withTimestamps();
    }

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_users')
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    public function currentCompany(): ?Company
    {
        if ($this->relationLoaded('currentCompany')) {
            return $this->getRelation('currentCompany');
        }

        return app(CurrentCompany::class)->get();
    }

    public function belongsToCompany(Company|int $company): bool
    {
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        if ($this->relationLoaded('companies')) {
            return $this->companies->contains(
                fn (Company $item): bool => $item->getKey() === $companyId
                    && (bool) data_get($item, 'pivot.is_active', true)
            );
        }

        return $this->companies()
            ->wherePivot('is_active', true)
            ->where('companies.id', $companyId)
            ->exists();
    }

    public function hasCompanyPermission(string $permission, Company|int|null $company = null): bool
    {
        $companyId = match (true) {
            $company instanceof Company => $company->getKey(),
            is_int($company) => $company,
            default => $this->currentCompany()?->getKey(),
        };

        if ($companyId === null) {
            return false;
        }

        $membership = $this->companyMemberships()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('role.permissions')
            ->first();

        if (! $membership || ! $membership->role instanceof Role) {
            return false;
        }

        return $membership->role->permissions->contains('slug', $permission);
    }
}
