<?php

namespace App\Domain\Conversations\Models;

use App\Domain\Tenants\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'avatar_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
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
