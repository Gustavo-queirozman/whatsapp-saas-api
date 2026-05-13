<?php

namespace App\Services\EvolutionGateway;

use Illuminate\Support\Carbon;

class EvolutionMessageMetadataResolver
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function extractExternalId(array $response): ?string
    {
        $externalId = data_get($response, 'key.id')
            ?? data_get($response, 'message.key.id')
            ?? data_get($response, 'messageId')
            ?? data_get($response, 'id');

        return is_string($externalId) && $externalId !== '' ? $externalId : null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function resolveSentAt(array $response): ?Carbon
    {
        $timestamp = data_get($response, 'messageTimestamp')
            ?? data_get($response, 'message.messageTimestamp')
            ?? data_get($response, 'timestamp');

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        if (is_string($timestamp) && $timestamp !== '') {
            return Carbon::parse($timestamp);
        }

        return null;
    }
}
