<?php

namespace App\Domain\Queues\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sector extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function whatsappInstances(): HasMany
    {
        return $this->hasMany(WhatsappInstance::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sector_users')
            ->withPivot('company_id')
            ->withTimestamps();
    }
}
