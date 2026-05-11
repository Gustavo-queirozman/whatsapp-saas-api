<?php

namespace App\Domain\Conversations\Models;

use App\Domain\Queues\Models\Sector;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'sector_id',
        'whatsapp_instance_id',
        'contact_id',
        'assigned_user_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
