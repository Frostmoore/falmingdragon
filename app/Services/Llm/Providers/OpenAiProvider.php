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
 * OpenAI provider.
 */
class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $apiBase;

    public function __construct(
        string $apiKey,
        string $model = 'gpt-4o',
        string $apiBase = 'https://api.openai.com/v1',
    ) {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        try {
            $payload = [
                'model'    => $options['model'] ?? $this->model,
                'messages' => $messages,
            ];

            if (! empty($tools)) {
                // Convert Anthropic-style tool definitions to OpenAI format if needed
                $payload['tools'] = array_map(static function (array $tool) {
                    return [
                        'type'     => 'function',
                        'function' => [
                            'name'        => $tool['name'],
                            'description' => $tool['description'] ?? '',
                            'parameters'  => $tool['input_schema'] ?? $tool['parameters'] ?? [],
                        ],
                    ];
                }, $tools);
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])->timeout(120)->post("{$this->apiBase}/chat/completions", $payload);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "OpenAI API error {$response->status()}: {$response->body()}"
                );
            }

            $data    = $response->json();
            $choice  = $data['choices'][0] ?? [];
            $message = $choice['message'] ?? [];

            $content   = $message['content'] ?? '';
            $toolCalls = [];

            foreach ($message['tool_calls'] ?? [] as $tc) {
                $toolCalls[] = [
                    'id'        => $tc['id'],
                    'name'      => $tc['function']['name'],
                    'arguments' => json_decode($tc['function']['arguments'] ?? '{}', true) ?? [],
                ];
            }

            $stopReason = match($choice['finish_reason'] ?? '') {
                'tool_calls' => 'tool_use',
                'length'     => 'max_tokens',
                default      => 'end_turn',
            };

            return new LlmResponse(
                content:      $content ?? '',
                toolCalls:    $toolCalls,
                inputTokens:  $data['usage']['prompt_tokens'] ?? 0,
                outputTokens: $data['usage']['completion_tokens'] ?? 0,
                stopReason:   $stopReason,
            );
        } catch (Throwable $e) {
            Log::error('[OpenAiProvider] chat() failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAvailableModels(): array
    {
        return ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'];
    }

    public function countTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
