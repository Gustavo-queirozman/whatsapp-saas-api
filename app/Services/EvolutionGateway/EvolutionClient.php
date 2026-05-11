<?php

namespace App\Services\EvolutionGateway;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use LogicException;
use RuntimeException;

class EvolutionClient
{
    private $http;

    public function __construct(
        Factory $http
    ) {
        $this->http = $http;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $webhook
     * @return array<string, mixed>
     */
    public function createInstance(
        string $instanceName,
        bool $qrcode = true,
        ?string $number = null,
        ?string $token = null,
        ?array $webhook = null,
        array $settings = [],
        ?string $integration = null
    ): array {
        $payload = [
            'instanceName' => $instanceName,
            'qrcode' => $qrcode,
            'integration' => $integration ?? $this->defaultIntegration(),
            'token' => $token,
            'number' => $number,
            'webhook' => $webhook,
        ];

        return $this->post(
            '/instance/create',
            array_merge($this->filterNullValues($payload), $settings),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getQrCode(string $instanceName, ?string $number = null): array
    {
        return $this->get(
            sprintf('/instance/connect/%s', rawurlencode($instanceName)),
            $this->filterNullValues([
                'number' => $number,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getInstanceStatus(string $instanceName): array
    {
        return $this->get(sprintf('/instance/connectionState/%s', rawurlencode($instanceName)));
    }

    /**
     * @return array<string, mixed>
     */
    public function disconnectInstance(string $instanceName): array
    {
        return $this->delete(sprintf('/instance/logout/%s', rawurlencode($instanceName)));
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteInstance(string $instanceName): array
    {
        return $this->delete(sprintf('/instance/delete/%s', rawurlencode($instanceName)));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendTextMessage(
        string $instanceName,
        string $number,
        string $text,
        array $options = []
    ): array {
        $payload = [
            'number' => $number,
            'textMessage' => [
                'text' => $text,
            ],
            'options' => $options === [] ? null : $options,
        ];

        return $this->post(
            sprintf('/message/sendText/%s', rawurlencode($instanceName)),
            $this->filterNullValues($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendMediaMessage(
        string $instanceName,
        string $number,
        string $mediaUrl,
        ?string $caption = null,
        array $options = []
    ): array {
        unset($instanceName, $number, $mediaUrl, $caption, $options);

        throw new LogicException('Envio de midia ainda nao foi implementado.');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        $response = $this->request()->get($endpoint, $query)->throw();

        return $this->responseData($response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        $response = $this->request()->post($endpoint, $payload)->throw();

        return $this->responseData($response->json());
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(string $endpoint): array
    {
        $response = $this->request()->delete($endpoint)->throw();

        return $this->responseData($response->json());
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->withHeaders([
                'apikey' => $this->apiKey(),
            ]);
    }

    private function baseUrl(): string
    {
        $baseUrl = (string) config('evolution.base_url');

        if ($baseUrl === '') {
            throw new RuntimeException('EVOLUTION_BASE_URL nao esta configurada.');
        }

        return rtrim($baseUrl, '/');
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('evolution.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('EVOLUTION_API_KEY nao esta configurada.');
        }

        return $apiKey;
    }

    private function defaultIntegration(): string
    {
        return (string) config('evolution.default_integration', 'WHATSAPP-BAILEYS');
    }

    private function timeout(): int
    {
        return (int) config('evolution.timeout', 15);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterNullValues(array $payload): array
    {
        return array_filter(
            $payload,
            static function ($value): bool {
                return $value !== null;
            },
        );
    }

    /**
     * @param  mixed  $response
     * @return array<string, mixed>
     */
    private function responseData($response): array
    {
        return is_array($response) ? $response : [];
    }
}
