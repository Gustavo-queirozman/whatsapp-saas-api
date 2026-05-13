<?php

namespace App\Services\Ai;

use App\DTOs\Ai\AiProviderResponseData;
use App\DTOs\Ai\ConversationAiRequestData;
use App\DTOs\Ai\MessageIntentRequestData;
use Illuminate\Support\Str;

class FakeAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'fake';
    }

    public function summarizeConversation(ConversationAiRequestData $data): AiProviderResponseData
    {
        $excerpt = $this->lastTranscriptLines($data->transcript, 3);
        $summary = sprintf(
            'Resumo automático: conversa com %d mensagens. Últimas interações: %s',
            $data->messageCount,
            $excerpt !== '' ? $excerpt : 'sem conteúdo textual disponível.',
        );

        return new AiProviderResponseData(
            content: $summary,
            model: 'fake-summary-v1',
        );
    }

    public function suggestReply(ConversationAiRequestData $data): AiProviderResponseData
    {
        $reference = $this->extractLatestCustomerMessage($data->transcript);

        $reply = $reference === ''
            ? 'Olá! Recebi sua mensagem e vou verificar o seu caso agora.'
            : sprintf(
                'Olá! Recebi sua mensagem sobre "%s" e vou verificar isso agora para te ajudar.',
                Str::limit($reference, 80, '...'),
            );

        return new AiProviderResponseData(
            content: $reply,
            model: 'fake-suggest-v1',
        );
    }

    public function classifyIntent(MessageIntentRequestData $data): AiProviderResponseData
    {
        $content = Str::lower($data->messageBody);

        $intent = match (true) {
            $this->containsAny($content, ['boleto', 'fatura', 'pagamento', 'financeiro', 'cobranca', 'cobrança']) => 'financeiro',
            $this->containsAny($content, ['preco', 'preço', 'plano', 'comprar', 'contratar', 'orcamento', 'orçamento', 'demo']) => 'vendas',
            $this->containsAny($content, ['erro', 'problema', 'suporte', 'ajuda', 'nao funciona', 'não funciona', 'falha', 'bug']) => 'suporte',
            default => 'outros',
        };

        return new AiProviderResponseData(
            content: $intent,
            model: 'fake-classifier-v1',
        );
    }

    private function extractLatestCustomerMessage(string $transcript): string
    {
        $lines = array_reverse(array_filter(array_map('trim', explode("\n", $transcript))));

        foreach ($lines as $line) {
            if (str_contains($line, 'cliente:')) {
                return trim((string) Str::after($line, 'cliente:'));
            }
        }

        return '';
    }

    private function lastTranscriptLines(string $transcript, int $limit): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $transcript)));

        if ($lines === []) {
            return '';
        }

        return implode(' | ', array_slice($lines, -1 * $limit));
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
