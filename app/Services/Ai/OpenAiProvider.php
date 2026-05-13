<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiProviderResponseData;
use App\DTOs\Ai\ConversationAiRequestData;
use App\DTOs\Ai\MessageIntentRequestData;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly Factory $http,
    ) {
    }

    public function name(): string
    {
        return 'openai';
    }

    public function summarizeConversation(ConversationAiRequestData $data): AiProviderResponseData
    {
        $model = $this->modelFor('summary');

        return $this->complete(
            model: $model,
            systemPrompt: 'Voce resume conversas de atendimento WhatsApp em pt-BR. Gere um resumo curto, objetivo e util para o atendente continuar o atendimento.',
            userPrompt: sprintf(
                "Contato: %s\nSetor: %s\nQuantidade de mensagens: %d\n\nTranscricao:\n%s",
                $data->contactName ?? 'Nao informado',
                $data->sectorName ?? 'Nao informado',
                $data->messageCount,
                $data->transcript,
            ),
        );
    }

    public function suggestReply(ConversationAiRequestData $data): AiProviderResponseData
    {
        $model = $this->modelFor('suggest_reply');

        return $this->complete(
            model: $model,
            systemPrompt: 'Voce apoia atendentes humanos. Gere apenas uma sugestao de resposta em pt-BR, pronta para enviar, objetiva, educada e sem explicacoes extras.',
            userPrompt: sprintf(
                "Contato: %s\nSetor: %s\n\nTranscricao:\n%s",
                $data->contactName ?? 'Nao informado',
                $data->sectorName ?? 'Nao informado',
                $data->transcript,
            ),
        );
    }

    public function classifyIntent(MessageIntentRequestData $data): AiProviderResponseData
    {
        $model = $this->modelFor('classify_intent');

        return $this->complete(
            model: $model,
            systemPrompt: 'Classifique a intencao principal da mensagem. Responda somente com uma palavra em minusculas: vendas, suporte, financeiro ou outros.',
            userPrompt: sprintf(
                "Mensagem alvo:\n%s\n\nContexto recente:\n%s",
                $data->messageBody,
                $data->transcript,
            ),
        );
    }

    private function complete(string $model, string $systemPrompt, string $userPrompt): AiProviderResponseData
    {
        $response = $this->request()->post('/responses', [
            'model' => $model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $systemPrompt,
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $userPrompt,
                        ],
                    ],
                ],
            ],
        ])->throw()->json();

        if (! is_array($response)) {
            throw new RuntimeException('A resposta da OpenAI e invalida.');
        }

        return new AiProviderResponseData(
            content: $this->extractText($response),
            model: data_get($response, 'model', $model),
            promptTokens: $this->integerOrNull(data_get($response, 'usage.input_tokens'))
                ?? $this->integerOrNull(data_get($response, 'usage.prompt_tokens')),
            completionTokens: $this->integerOrNull(data_get($response, 'usage.output_tokens'))
                ?? $this->integerOrNull(data_get($response, 'usage.completion_tokens')),
            totalTokens: $this->integerOrNull(data_get($response, 'usage.total_tokens')),
            raw: $response,
        );
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.providers.openai.timeout', 30))
            ->withToken($this->apiKey());
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY nao esta configurada.');
        }

        return $apiKey;
    }

    private function baseUrl(): string
    {
        $baseUrl = (string) config('services.openai.base_url', 'https://api.openai.com/v1');

        if ($baseUrl === '') {
            throw new RuntimeException('OPENAI_BASE_URL nao esta configurada.');
        }

        return rtrim($baseUrl, '/');
    }

    private function modelFor(string $operation): string
    {
        $model = (string) config(sprintf('ai.providers.openai.models.%s', $operation));

        if ($model === '') {
            throw new RuntimeException(sprintf('Modelo OpenAI nao configurado para a operacao %s.', $operation));
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractText(array $response): string
    {
        $outputText = data_get($response, 'output_text');

        if (is_string($outputText) && trim($outputText) !== '') {
            return trim($outputText);
        }

        $texts = [];

        foreach ((array) data_get($response, 'output', []) as $item) {
            foreach ((array) data_get($item, 'content', []) as $content) {
                $text = data_get($content, 'text') ?? data_get($content, 'content');

                if (is_string($text) && trim($text) !== '') {
                    $texts[] = trim($text);
                }
            }
        }

        $text = trim(implode("\n", $texts));

        if ($text === '') {
            throw new RuntimeException('A resposta da OpenAI nao retornou texto.');
        }

        return $text;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
