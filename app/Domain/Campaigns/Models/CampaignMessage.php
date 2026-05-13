<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'campaign_id',
        'campaign_contact_id',
        'whatsapp_instance_id',
        'status',
        'phone',
        'external_id',
        'body',
        'response_payload',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class);
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }
}
