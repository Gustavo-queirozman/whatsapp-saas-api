<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\Models\BotFlow;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;

class CreateBotFlowAction
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): BotFlow
    {
        $companyId = $this->currentCompany->id();
        abort_if($companyId === null, 403);

        return DB::transaction(function () use ($data, $companyId): BotFlow {
            $options = $data['options'] ?? [];
            unset($data['options']);

            $botFlow = BotFlow::query()->create(array_merge($data, [
                'company_id' => $companyId,
            ]));

            $this->syncOptions($botFlow, $options, $companyId);
            $this->deactivateOtherFlows($botFlow);

            return $botFlow->fresh(['sector', 'options.targetSector']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(BotFlow $botFlow, array $options, int $companyId): void
    {
        foreach ($options as $index => $option) {
            $botFlow->options()->create([
                'company_id' => $companyId,
                'label' => (string) $option['label'],
                'number' => $option['number'] ?? null,
                'keywords' => $option['keywords'] ?? [],
                'action' => (string) $option['action'],
                'response_message' => $option['response_message'] ?? null,
                'target_sector_id' => $option['target_sector_id'] ?? null,
                'sort_order' => (int) ($option['sort_order'] ?? $index),
                'is_active' => (bool) ($option['is_active'] ?? true),
                'settings' => $option['settings'] ?? [],
            ]);
        }
    }

    private function deactivateOtherFlows(BotFlow $botFlow): void
    {
        if (! $botFlow->is_active) {
            return;
        }

        BotFlow::query()
            ->where('sector_id', $botFlow->sector_id)
            ->whereKeyNot($botFlow->id)
            ->update(['is_active' => false]);
    }
}
