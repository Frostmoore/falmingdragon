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
 * Ollama local provider (OpenAI-compatible chat endpoint).
 */
class OllamaProvider implements LlmProviderInterface
{
    private string $model;
    private string $apiBase;

    public function __construct(
        string $model = 'llama3',
        string $apiBase = 'http://localhost:11434',
    ) {
        $this->model   = $model;
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        try {
            $payload = [
                'model'    => $options['model'] ?? $this->model,
                'messages' => $messages,
                'stream'   => false,
            ];

            $response = Http::timeout(300)->post("{$this->apiBase}/api/chat", $payload);

            if (! $response->successful()) {
                throw new RuntimeException(
                    "Ollama API error {$response->status()}: {$response->body()}"
                );
            }

            $data    = $response->json();
            $content = $data['message']['content'] ?? '';

            return new LlmResponse(
                content:      $content,
                toolCalls:    [],
                inputTokens:  $data['prompt_eval_count'] ?? 0,
                outputTokens: $data['eval_count'] ?? 0,
                stopReason:   'end_turn',
            );
        } catch (Throwable $e) {
            Log::error('[OllamaProvider] chat() failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAvailableModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiBase}/api/tags");
            if ($response->successful()) {
                return array_column($response->json('models', []), 'name');
            }
        } catch (Throwable) {
            // Fallback to defaults if Ollama is not running
        }

        return ['llama3', 'mistral', 'phi3'];
    }

    public function countTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
