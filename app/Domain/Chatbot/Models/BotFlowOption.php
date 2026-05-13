<?php

namespace App\Domain\Chatbot\Models;

use App\Domain\Queues\Models\Sector;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotFlowOption extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const ACTION_REPLY = 'reply';

    public const ACTION_TRANSFER_SECTOR = 'transfer_sector';

    public const ACTION_OPEN_QUEUE = 'open_queue';

    protected $fillable = [
        'company_id',
        'bot_flow_id',
        'target_sector_id',
        'label',
        'number',
        'keywords',
        'action',
        'response_message',
        'sort_order',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function botFlow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class);
    }

    public function targetSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'target_sector_id');
    }
}
