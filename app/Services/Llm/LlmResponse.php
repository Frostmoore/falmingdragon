<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Data Transfer Object returned by every Large Language Model provider.
 */
final class LlmResponse
{
    /**
     * @param  string                               $content      Text content of the response.
     * @param  array<int, array<string, mixed>>     $toolCalls    Requested tool calls: [{name, arguments}].
     * @param  int                                  $inputTokens  Tokens consumed by the prompt.
     * @param  int                                  $outputTokens Tokens produced in the completion.
     * @param  string                               $stopReason   'end_turn' | 'tool_use' | 'max_tokens'
     */
    public function __construct(
        public readonly string $content,
        public readonly array  $toolCalls,
        public readonly int    $inputTokens,
        public readonly int    $outputTokens,
        public readonly string $stopReason,
    ) {}

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function isComplete(): bool
    {
        return $this->stopReason === 'end_turn';
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
