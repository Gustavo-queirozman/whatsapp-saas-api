<?php

namespace App\Providers;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Chatbot\Models\BotFlow;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\Workspace;
use App\Domain\Conversations\Models\Contact;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Crm\Models\Deal;
use App\Domain\Crm\Models\Pipeline;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Queues\Models\Sector;
use App\Domain\Tags\Models\Tag;
use App\Domain\WhatsApp\Models\WhatsappInstance;
use App\Policies\CampaignPolicy;
use App\Policies\BotFlowPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\DealPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PipelinePolicy;
use App\Policies\PipelineStagePolicy;
use App\Policies\SectorPolicy;
use App\Policies\TagPolicy;
use App\Policies\WhatsappInstancePolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Workspace::class => WorkspacePolicy::class,
        Campaign::class => CampaignPolicy::class,
        BotFlow::class => BotFlowPolicy::class,
        WhatsappInstance::class => WhatsappInstancePolicy::class,
        Contact::class => ContactPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        Pipeline::class => PipelinePolicy::class,
        PipelineStage::class => PipelineStagePolicy::class,
        Deal::class => DealPolicy::class,
        Sector::class => SectorPolicy::class,
        Tag::class => TagPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
