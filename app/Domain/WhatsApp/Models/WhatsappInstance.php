<?php

namespace App\Domain\WhatsApp\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Queues\Models\Sector;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappInstance extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'sector_id',
        'instance_name',
        'phone_number',
        'status',
        'last_connection_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_connection_at' => 'datetime',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
