<?php

namespace Tests\Unit\Services\EvolutionGateway;

use App\Services\EvolutionGateway\EvolutionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class EvolutionClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('evolution.base_url', 'https://evolution.test');
        config()->set('evolution.api_key', 'test-api-key');
        config()->set('evolution.default_integration', 'WHATSAPP-BAILEYS');
        config()->set('evolution.timeout', 15);

        Http::preventStrayRequests();
    }

    public function test_it_creates_a_whatsapp_instance(): void
    {
        Http::fake([
            'https://evolution.test/instance/create' => Http::response([
                'instance' => [
                    'instanceName' => 'empresa-a',
                    'status' => 'connecting',
                ],
                'qrcode' => [
                    'code' => 'qr-code-value',
                ],
            ], 201),
        ]);

        $response = app(EvolutionClient::class)->createInstance(
            'empresa-a',
            true,
            '5511999999999',
            null,
            [
                'enabled' => true,
                'url' => 'https://app.test/webhook/evolution',
                'events' => ['MESSAGES_UPSERT'],
            ],
            [
                'rejectCall' => true,
                'alwaysOnline' => true,
            ],
        );

        $this->assertSame('empresa-a', $response['instance']['instanceName']);
        $this->assertSame('qr-code-value', $response['qrcode']['code']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/instance/create'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request['instanceName'] === 'empresa-a'
                && $request['integration'] === 'WHATSAPP-BAILEYS'
                && $request['number'] === '5511999999999'
                && $request['rejectCall'] === true
                && $request['alwaysOnline'] === true
                && $request['webhook']['url'] === 'https://app.test/webhook/evolution';
        });
    }

    public function test_it_fetches_qr_code_and_connection_status(): void
    {
        Http::fake([
            'https://evolution.test/instance/connect/empresa-a*' => Http::response([
                'pairingCode' => 'ABC123',
                'code' => 'base64-qr',
            ], 200),
            'https://evolution.test/instance/connectionState/empresa-a' => Http::response([
                'instance' => [
                    'instanceName' => 'empresa-a',
                    'state' => 'open',
                ],
            ], 200),
        ]);

        $client = app(EvolutionClient::class);

        $qrCode = $client->getQrCode('empresa-a', '5511999999999');
        $status = $client->getInstanceStatus('empresa-a');

        $this->assertSame('ABC123', $qrCode['pairingCode']);
        $this->assertSame('open', $status['instance']['state']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://evolution.test/instance/connect/empresa-a?number=5511999999999'
                && $request->hasHeader('apikey', 'test-api-key');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://evolution.test/instance/connectionState/empresa-a'
                && $request->hasHeader('apikey', 'test-api-key');
        });
    }

    public function test_it_disconnects_and_removes_an_instance(): void
    {
        Http::fake([
            'https://evolution.test/instance/logout/empresa-a' => Http::response([
                'status' => 'SUCCESS',
                'error' => false,
                'response' => [
                    'message' => 'Instance logged out',
                ],
            ], 200),
            'https://evolution.test/instance/delete/empresa-a' => Http::response([
                'status' => 'SUCCESS',
                'error' => false,
                'response' => [
                    'message' => 'Instance deleted',
                ],
            ], 200),
        ]);

        $client = app(EvolutionClient::class);

        $disconnectResponse = $client->disconnectInstance('empresa-a');
        $deleteResponse = $client->deleteInstance('empresa-a');

        $this->assertSame('Instance logged out', $disconnectResponse['response']['message']);
        $this->assertSame('Instance deleted', $deleteResponse['response']['message']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://evolution.test/instance/logout/empresa-a'
                && $request->hasHeader('apikey', 'test-api-key');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://evolution.test/instance/delete/empresa-a'
                && $request->hasHeader('apikey', 'test-api-key');
        });
    }

    public function test_it_sends_a_text_message(): void
    {
        Http::fake([
            'https://evolution.test/message/sendText/empresa-a' => Http::response([
                'status' => 'PENDING',
                'message' => [
                    'extendedTextMessage' => [
                        'text' => 'Ola!',
                    ],
                ],
            ], 201),
        ]);

        $response = app(EvolutionClient::class)->sendTextMessage(
            'empresa-a',
            '5511999999999',
            'Ola!',
            [
                'delay' => 500,
                'presence' => 'composing',
            ],
        );

        $this->assertSame('PENDING', $response['status']);
        $this->assertSame('Ola!', $response['message']['extendedTextMessage']['text']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://evolution.test/message/sendText/empresa-a'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request['number'] === '5511999999999'
                && $request['textMessage']['text'] === 'Ola!'
                && $request['options']['delay'] === 500
                && $request['options']['presence'] === 'composing';
        });
    }

    public function test_it_exposes_a_media_method_placeholder(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Envio de midia ainda nao foi implementado.');

        app(EvolutionClient::class)->sendMediaMessage(
            'empresa-a',
            '5511999999999',
            'https://cdn.test/file.jpg',
            'Arquivo',
        );
    }
}
