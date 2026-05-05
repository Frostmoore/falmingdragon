<?php

declare(strict_types=1);

namespace App\Services\Llm\Providers;

use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\LlmResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Anthropic Claude provider.
 */
class AnthropicProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $apiBase;

    public function __construct(
        string $apiKey,
        string $model = 'claude-sonnet-4-20250514',
        string $apiBase = 'https://api.anthropic.com/v1',
    ) {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        try {
            $payload = [
                'model'      => $options['model'] ?? $this->model,
                'max_tokens' => $options['max_tokens'] ?? config('flamingdragon.llm.max_tokens', 4096),
                'messages'   => $this->formatMessages($messages),
            ];

            if (! empty($options['system'])) {
                $payload['system'] = $options['system'];
            }

            if (! empty($tools)) {
                $payload['tools'] = $tools;
            }

            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(120)->post("{$this->apiBase}/messages", $payload);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Anthropic API error {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            $content   = '';
            $toolCalls = [];
            $rawBlocks = $data['content'] ?? [];

            foreach ($rawBlocks as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id'        => $block['id'],
                        'name'      => $block['name'],
                        'arguments' => $block['input'] ?? [],
                    ];
                }
            }

            return new LlmResponse(
                content:      $content,
                toolCalls:    $toolCalls,
                inputTokens:  $data['usage']['input_tokens'] ?? 0,
                outputTokens: $data['usage']['output_tokens'] ?? 0,
                stopReason:   $data['stop_reason'] ?? 'end_turn',
                rawBlocks:    $rawBlocks,
            );
        } catch (Throwable $e) {
            Log::error('[AnthropicProvider] chat() failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAvailableModels(): array
    {
        return [
            'claude-opus-4-20250514',
            'claude-sonnet-4-20250514',
            'claude-haiku-4-5-20251001',
        ];
    }

    public function countTokens(string $text): int
    {
        // Approximate: ~4 characters per token
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Ensure messages alternate between user and assistant roles as Anthropic requires.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        // Filter to only user/assistant messages (system handled separately)
        return array_values(array_filter($messages, static function (array $m) {
            return in_array($m['role'] ?? '', ['user', 'assistant'], true);
        }));
    }
}
