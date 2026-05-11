<?php

namespace App\Domain\WhatsApp\Models;

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Tenants\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappInstance extends Model
{
    use HasFactory;

    protected $fillable = [
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

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
