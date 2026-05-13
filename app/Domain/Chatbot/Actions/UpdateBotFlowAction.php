<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\Models\BotFlow;
use Illuminate\Support\Facades\DB;

class UpdateBotFlowAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(BotFlow $botFlow, array $data): BotFlow
    {
        return DB::transaction(function () use ($botFlow, $data): BotFlow {
            $options = $data['options'] ?? [];
            unset($data['options']);

            $botFlow->fill($data);
            $botFlow->save();

            $botFlow->options()->delete();

            foreach ($options as $index => $option) {
                $botFlow->options()->create([
                    'company_id' => $botFlow->company_id,
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

            if ($botFlow->is_active) {
                BotFlow::query()
                    ->where('sector_id', $botFlow->sector_id)
                    ->whereKeyNot($botFlow->id)
                    ->update(['is_active' => false]);
            }

            return $botFlow->fresh(['sector', 'options.targetSector']);
        });
    }
}
