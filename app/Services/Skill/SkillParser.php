<?php

declare(strict_types=1);

namespace App\Services\Skill;

use RuntimeException;

/**
 * Parses SKILL.md files and extracts structured metadata from the YAML front-matter.
 */
class SkillParser
{
    /**
     * Parse a SKILL.md file and return metadata + body.
     *
     * @param  string  $filePath  Absolute path to the SKILL.md file.
     * @return array{name: string, description: string, version: string, tools_required: array<string>, body: string}
     * @throws RuntimeException
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("SKILL.md not found at: {$filePath}");
        }

        $raw = file_get_contents($filePath);

        if ($raw === false) {
            throw new RuntimeException("Unable to read SKILL.md at: {$filePath}");
        }

        return $this->parseContent($raw);
    }

    /**
     * Parse raw SKILL.md content.
     *
     * @param  string  $content
     * @return array{name: string, description: string, version: string, tools_required: array<string>, body: string}
     */
    public function parseContent(string $content): array
    {
        $frontMatter = [];
        $body        = $content;

        // Extract YAML front-matter between --- delimiters
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
            $frontMatter = $this->parseYamlSimple($matches[1]);
            $body        = trim($matches[2]);
        }

        return [
            'name'           => $frontMatter['name'] ?? '',
            'description'    => $frontMatter['description'] ?? '',
            'version'        => $frontMatter['version'] ?? '1.0.0',
            'tools_required' => $this->parseList($frontMatter['tools_required'] ?? ''),
            'body'           => $body,
        ];
    }

    /**
     * Simple YAML key-value parser (supports strings and inline arrays).
     *
     * @param  string  $yaml
     * @return array<string, mixed>
     */
    private function parseYamlSimple(string $yaml): array
    {
        $result = [];
        foreach (explode("\n", $yaml) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $result[trim($key)] = trim($value, " \t\"'");
        }
        return $result;
    }

    /**
     * Parse a YAML inline list like ["bash", "file_write"] or a plain string.
     *
     * @param  string|array<string>  $raw
     * @return array<string>
     */
    private function parseList(string|array $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        // Match ["item1", "item2"] syntax
        if (preg_match('/\[([^\]]*)\]/', $raw, $m)) {
            return array_filter(
                array_map(
                    static fn (string $s) => trim($s, " \t\"'"),
                    explode(',', $m[1])
                )
            );
        }

        return array_filter([trim($raw, " \t\"'")]);
    }
}
