<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Contract that every Large Language Model provider must implement.
 */
interface LlmProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param  array<int, array<string, mixed>>  $messages   Conversation history in {role, content} format.
     * @param  array<int, array<string, mixed>>  $tools      Tool definitions available for this call.
     * @param  array<string, mixed>              $options    Provider-specific overrides (temperature, max_tokens, …).
     * @return LlmResponse
     */
    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse;

    /**
     * Return the list of model identifiers available for this provider.
     *
     * @return array<string>
     */
    public function getAvailableModels(): array;

    /**
     * Approximate token count for a string (used for budget estimation).
     *
     * @param  string  $text
     * @return int
     */
    public function countTokens(string $text): int;
}
