<?php

namespace App\DTOs\WhatsApp;

use Carbon\CarbonImmutable;

class EvolutionWebhookPayloadData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?string $event,
        public readonly ?string $instanceName,
        public readonly ?string $externalId,
        public readonly ?string $remoteJid,
        public readonly ?string $contactPhone,
        public readonly ?string $contactName,
        public readonly bool $fromMe,
        public readonly string $messageType,
        public readonly ?string $body,
        public readonly ?CarbonImmutable $sentAt,
        public readonly array $payload,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $messageEnvelope = self::extractMessageEnvelope($payload);
        $keyPayload = self::arrayValue(data_get($messageEnvelope, 'key', data_get($payload, 'key', [])));
        $messagePayload = self::unwrapMessage(
            self::arrayValue(data_get($messageEnvelope, 'message', data_get($payload, 'message', [])))
        );

        $rawMessageType = self::stringValue(
            data_get($messageEnvelope, 'messageType')
                ?? data_get($payload, 'messageType')
                ?? self::detectMessageType($messagePayload)
        );

        $remoteJid = self::stringValue(data_get($keyPayload, 'remoteJid') ?? data_get($messageEnvelope, 'remoteJid'));

        return new self(
            self::stringValue(data_get($payload, 'event') ?? data_get($payload, 'eventType')),
            self::resolveInstanceName($payload),
            self::stringValue(data_get($keyPayload, 'id') ?? data_get($messageEnvelope, 'id') ?? data_get($payload, 'id')),
            $remoteJid,
            self::normalizePhone($remoteJid),
            self::stringValue(data_get($messageEnvelope, 'pushName') ?? data_get($payload, 'pushName')),
            self::booleanValue(data_get($keyPayload, 'fromMe') ?? data_get($messageEnvelope, 'fromMe') ?? data_get($payload, 'fromMe')),
            self::normalizeMessageType($rawMessageType),
            self::extractBody($messagePayload),
            self::resolveSentAt(
                data_get($messageEnvelope, 'messageTimestamp')
                    ?? data_get($payload, 'messageTimestamp')
                    ?? data_get($messageEnvelope, 'timestamp')
                    ?? data_get($payload, 'timestamp')
            ),
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function extractMessageEnvelope(array $payload): array
    {
        $dataPayload = self::arrayValue(data_get($payload, 'data', []));

        if ($dataPayload !== [] && self::arrayValue(data_get($dataPayload, 'key', [])) !== []) {
            return $dataPayload;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     * @return array<string, mixed>
     */
    private static function unwrapMessage(array $messagePayload): array
    {
        $currentPayload = $messagePayload;

        while (true) {
            $ephemeralPayload = self::arrayValue(data_get($currentPayload, 'ephemeralMessage.message', []));

            if ($ephemeralPayload !== []) {
                $currentPayload = $ephemeralPayload;

                continue;
            }

            $viewOncePayload = self::arrayValue(data_get($currentPayload, 'viewOnceMessage.message', []));

            if ($viewOncePayload !== []) {
                $currentPayload = $viewOncePayload;

                continue;
            }

            return $currentPayload;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveInstanceName(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'instance'),
            data_get($payload, 'instanceName'),
            data_get($payload, 'sender'),
            data_get($payload, 'data.instance'),
            data_get($payload, 'data.instanceName'),
        ];

        foreach ($candidates as $candidate) {
            $value = self::stringValue($candidate);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     */
    private static function detectMessageType(array $messagePayload): ?string
    {
        $firstKey = array_key_first($messagePayload);

        return is_string($firstKey) ? $firstKey : null;
    }

    private static function normalizeMessageType(?string $messageType): string
    {
        $normalizedType = strtolower(trim((string) $messageType));

        return match ($normalizedType) {
            '', 'conversation', 'extendedtextmessage' => 'text',
            'imagemessage' => 'image',
            'videomessage' => 'video',
            'audiomessage' => 'audio',
            'documentmessage' => 'document',
            'stickermessage' => 'sticker',
            'locationmessage' => 'location',
            default => $normalizedType,
        };
    }

    /**
     * @param  array<string, mixed>  $messagePayload
     */
    private static function extractBody(array $messagePayload): ?string
    {
        $candidates = [
            data_get($messagePayload, 'conversation'),
            data_get($messagePayload, 'extendedTextMessage.text'),
            data_get($messagePayload, 'imageMessage.caption'),
            data_get($messagePayload, 'videoMessage.caption'),
            data_get($messagePayload, 'documentMessage.caption'),
            data_get($messagePayload, 'buttonsResponseMessage.selectedButtonId'),
            data_get($messagePayload, 'listResponseMessage.title'),
            data_get($messagePayload, 'templateButtonReplyMessage.selectedId'),
        ];

        foreach ($candidates as $candidate) {
            $value = self::stringValue($candidate);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  mixed  $value
     */
    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    /**
     * @param  mixed  $value
     */
    private static function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }

        return false;
    }

    private static function normalizePhone(?string $remoteJid): ?string
    {
        if ($remoteJid === null) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * @param  mixed  $value
     */
    private static function resolveSentAt(mixed $value): ?CarbonImmutable
    {
        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        if (is_string($value) && trim($value) !== '' && ctype_digit($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        return null;
    }
}
