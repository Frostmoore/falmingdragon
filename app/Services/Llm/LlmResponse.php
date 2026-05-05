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
     * @param  array<int, array<string, mixed>>     $toolCalls    Requested tool calls: [{id, name, arguments}].
     * @param  int                                  $inputTokens  Tokens consumed by the prompt.
     * @param  int                                  $outputTokens Tokens produced in the completion.
     * @param  string                               $stopReason   'end_turn' | 'tool_use' | 'max_tokens'
     * @param  array<int, array<string, mixed>>     $rawBlocks    Raw content blocks from provider (e.g. Anthropic).
     *                                                            Used to reconstruct the exact assistant message for multi-turn tool use.
     */
    public function __construct(
        public readonly string $content,
        public readonly array  $toolCalls,
        public readonly int    $inputTokens,
        public readonly int    $outputTokens,
        public readonly string $stopReason,
        public readonly array  $rawBlocks = [],
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
