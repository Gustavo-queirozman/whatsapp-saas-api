<?php

namespace Tests\Unit\Services\Ai;

use App\DTOs\Ai\ConversationAiRequestData;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.base_url', 'https://openai.test/v1');
        config()->set('ai.providers.openai.timeout', 20);
        config()->set('ai.providers.openai.models.summary', 'gpt-4.1-mini');
        config()->set('ai.providers.openai.models.suggest_reply', 'gpt-4.1-mini');
        config()->set('ai.providers.openai.models.classify_intent', 'gpt-4.1-mini');

        Http::preventStrayRequests();
    }

    public function test_it_sends_a_responses_request_and_parses_the_result(): void
    {
        Http::fake([
            'https://openai.test/v1/responses' => Http::response([
                'model' => 'gpt-4.1-mini',
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Resumo gerado pela IA.',
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 120,
                    'output_tokens' => 32,
                    'total_tokens' => 152,
                ],
            ], 200),
        ]);

        $response = app(OpenAiProvider::class)->summarizeConversation(
            new ConversationAiRequestData(
                transcript: "[2026-05-12 10:00:00] cliente: Estou com duvida no plano.\n[2026-05-12 10:01:00] atendente: Pode me contar mais?",
                messageCount: 2,
                contactName: 'Cliente Teste',
                sectorName: 'Vendas',
            ),
        );

        $this->assertSame('Resumo gerado pela IA.', $response->content);
        $this->assertSame('gpt-4.1-mini', $response->model);
        $this->assertSame(120, $response->promptTokens);
        $this->assertSame(32, $response->completionTokens);
        $this->assertSame(152, $response->totalTokens);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request['model'] === 'gpt-4.1-mini'
                && $request['input'][0]['role'] === 'system'
                && $request['input'][1]['role'] === 'user'
                && str_contains($request['input'][1]['content'][0]['text'], 'Cliente Teste');
        });
    }
}
