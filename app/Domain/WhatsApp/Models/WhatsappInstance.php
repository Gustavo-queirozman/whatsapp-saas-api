<?php

namespace App\Domain\WhatsApp\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Companies\Models\Workspace;
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
        'workspace_id',
        'name',
        'provider',
        'phone_number',
        'status',
        'settings',
        'connected_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'connected_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
