<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Models\LlmProvider as LlmProviderModel;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\OllamaProvider;
use App\Services\Llm\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Routes Large Language Model calls to the correct provider.
 */
class LlmRouter
{
    /**
     * Get the appropriate provider instance.
     *
     * @param  string|null  $providerName  Override provider name (null = use default).
     * @param  string|null  $model         Override model (null = use provider default).
     * @return LlmProviderInterface
     */
    public function getProvider(?string $providerName = null, ?string $model = null): LlmProviderInterface
    {
        $providerName ??= config('flamingdragon.llm.default_provider', 'anthropic');

        $record = LlmProviderModel::where('name', $providerName)
            ->where('is_active', true)
            ->first();

        if ($record === null) {
            Log::warning('[LlmRouter] Provider not found, falling back to anthropic.', [
                'requested' => $providerName,
            ]);
            $providerName = 'anthropic';
            $record       = LlmProviderModel::where('name', 'anthropic')->where('is_active', true)->first();
        }

        if ($record === null) {
            throw new RuntimeException('No active Large Language Model provider found.');
        }

        $resolvedModel = $model ?? config('flamingdragon.llm.default_model', $record->default_model);
        $apiKey        = $record->resolveApiKey() ?? '';

        return match($record->name) {
            'anthropic' => new AnthropicProvider($apiKey, $resolvedModel, $record->api_base_url),
            'openai'    => new OpenAiProvider($apiKey, $resolvedModel, $record->api_base_url),
            'ollama'    => new OllamaProvider($resolvedModel, $record->api_base_url),
            default     => throw new RuntimeException("Unknown provider: {$record->name}"),
        };
    }

    /**
     * Convenience wrapper — route and call chat in one step.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>              $options    May include 'provider' and 'model' keys.
     * @return LlmResponse
     */
    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        $provider = $this->getProvider(
            $options['provider'] ?? null,
            $options['model'] ?? null,
        );

        return $provider->chat($messages, $tools, $options);
    }
}
