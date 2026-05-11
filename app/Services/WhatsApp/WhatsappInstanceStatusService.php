<?php

namespace App\Services\WhatsApp;

use App\Domain\WhatsApp\Models\WhatsappInstance;
use Illuminate\Support\Carbon;

class WhatsappInstanceStatusService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromGateway(
        WhatsappInstance $whatsappInstance,
        array $payload,
        ?string $fallbackStatus = null
    ): WhatsappInstance {
        $status = $this->extractStatus($payload) ?? $fallbackStatus ?? $whatsappInstance->status;
        $normalizedStatus = $this->normalize($status, $whatsappInstance->status);

        $whatsappInstance->forceFill([
            'status' => $normalizedStatus,
            'last_connection_at' => $this->resolveLastConnectionAt(
                $normalizedStatus,
                $whatsappInstance->last_connection_at,
            ),
        ])->save();

        return $whatsappInstance->refresh();
    }

    public function normalize(?string $status, string $fallback = 'disconnected'): string
    {
        $value = strtolower(trim((string) $status));

        if ($value === '') {
            return $fallback;
        }

        return match ($value) {
            'open', 'connected' => 'connected',
            'close', 'closed', 'disconnected', 'logout', 'logged_out' => 'disconnected',
            'connecting', 'qr', 'qrcode', 'pairing_code', 'pairingcode' => 'connecting',
            default => $value,
        };
    }

    private function resolveLastConnectionAt(string $status, ?Carbon $currentValue): ?Carbon
    {
        return $status === 'connected' ? now() : $currentValue;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractStatus(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'instance.state'),
            data_get($payload, 'instance.status'),
            data_get($payload, 'state'),
            data_get($payload, 'status'),
        ];

        foreach ($candidates as $candidate) {
            if (
                is_string($candidate)
                && trim($candidate) !== ''
                && ! in_array(strtolower(trim($candidate)), ['success', 'error', 'ok'], true)
            ) {
                return $candidate;
            }
        }

        return null;
    }
}
