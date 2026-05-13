<?php

namespace App\Domain\Chatbot\Models;

use App\Domain\Queues\Models\Sector;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotFlow extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'sector_id',
        'name',
        'is_active',
        'welcome_message',
        'menu_message',
        'invalid_option_message',
        'out_of_hours_message',
        'office_hours_enabled',
        'office_hours_timezone',
        'office_hours',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'office_hours_enabled' => 'boolean',
        'office_hours' => 'array',
        'settings' => 'array',
    ];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(BotFlowOption::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
