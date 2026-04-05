<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LlmProvider;
use Illuminate\Database\Seeder;

class DefaultProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name'             => 'anthropic',
                'display_name'     => 'Anthropic (Claude)',
                'api_base_url'     => 'https://api.anthropic.com/v1',
                'api_key_env'      => 'ANTHROPIC_API_KEY',
                'default_model'    => 'claude-sonnet-4-20250514',
                'available_models' => [
                    'claude-opus-4-20250514',
                    'claude-sonnet-4-20250514',
                    'claude-haiku-4-5-20251001',
                ],
                'is_default' => true,
                'is_active'  => true,
                'config'     => [
                    'anthropic_version' => '2023-06-01',
                ],
            ],
            [
                'name'             => 'openai',
                'display_name'     => 'OpenAI',
                'api_base_url'     => 'https://api.openai.com/v1',
                'api_key_env'      => 'OPENAI_API_KEY',
                'default_model'    => 'gpt-4o',
                'available_models' => [
                    'gpt-4o',
                    'gpt-4o-mini',
                    'gpt-4-turbo',
                ],
                'is_default' => false,
                'is_active'  => true,
                'config'     => [],
            ],
            [
                'name'             => 'ollama',
                'display_name'     => 'Ollama (Local)',
                'api_base_url'     => env('FD_OLLAMA_BASE_URL', 'http://localhost:11434'),
                'api_key_env'      => null,
                'default_model'    => 'llama3',
                'available_models' => [
                    'llama3',
                    'mistral',
                    'phi3',
                ],
                'is_default' => false,
                'is_active'  => true,
                'config'     => [],
            ],
        ];

        foreach ($providers as $provider) {
            LlmProvider::updateOrCreate(
                ['name' => $provider['name']],
                $provider
            );
        }
    }
}
