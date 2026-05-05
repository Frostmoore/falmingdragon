<?php

declare(strict_types=1);

namespace App\Services\Embeddings;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates and compares text embeddings using OpenAI text-embedding-3-small.
 * Falls back gracefully when the API is unavailable.
 */
class EmbeddingService
{
    private const MODEL      = 'text-embedding-3-small';
    private const MAX_CHARS  = 8000; // ~2000 tokens, well within 8191 token limit

    public function __construct(
        private readonly string $apiKey = '',
    ) {}

    /**
     * Generate an embedding vector for the given text.
     * Returns null if the API key is missing or the call fails.
     *
     * @return array<float>|null
     */
    public function generate(string $text): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $text = mb_substr(trim($text), 0, self::MAX_CHARS);

        if ($text === '') {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => self::MODEL,
                    'input' => $text,
                ]);

            if ($response->successful()) {
                /** @var array<float> $embedding */
                $embedding = $response->json('data.0.embedding');
                return $embedding ?: null;
            }

            Log::warning('[EmbeddingService] API error.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (Throwable $e) {
            Log::warning('[EmbeddingService] Request failed.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Cosine similarity between two vectors. Returns 0.0–1.0.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot  += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        $denom = sqrt($magA) * sqrt($magB);
        return $denom > 0.0 ? (float) ($dot / $denom) : 0.0;
    }

    /** Returns true if the service is usable (API key present). */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }
}
