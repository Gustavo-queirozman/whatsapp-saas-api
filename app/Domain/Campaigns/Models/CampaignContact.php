<?php

namespace App\Domain\Campaigns\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignContact extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'campaign_id',
        'name',
        'phone',
        'status',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }
}
