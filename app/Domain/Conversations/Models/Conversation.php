<?php

namespace App\Domain\Conversations\Models;

use App\Domain\Tenants\Models\Workspace;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'whatsapp_instance_id',
        'contact_id',
        'assigned_user_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function whatsappInstance()
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
