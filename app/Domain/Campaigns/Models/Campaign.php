<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'company_id',
        'whatsapp_instance_id',
        'name',
        'message',
        'send_limit_per_minute',
        'status',
        'scheduled_at',
        'started_at',
        'paused_at',
        'finished_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    public function scopeWithSummary(Builder $query): Builder
    {
        return $query
            ->with('whatsappInstance')
            ->withCount(self::summaryCounts());
    }

    public static function summaryCounts(): array
    {
        return [
            'contacts as total_contacts',
            'contacts as pending_contacts' => fn (Builder $query): Builder => $query->where(
                'status',
                CampaignContact::STATUS_PENDING
            ),
            'contacts as processing_contacts' => fn (Builder $query): Builder => $query->where(
                'status',
                CampaignContact::STATUS_PROCESSING
            ),
            'contacts as success_contacts' => fn (Builder $query): Builder => $query->where(
                'status',
                CampaignContact::STATUS_SUCCESS
            ),
            'contacts as failed_contacts' => fn (Builder $query): Builder => $query->where(
                'status',
                CampaignContact::STATUS_FAILED
            ),
        ];
    }
}
