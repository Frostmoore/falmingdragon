<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmProvider extends Model
{
    protected $table = 'llm_providers';

    protected $fillable = [
        'name',
        'display_name',
        'api_base_url',
        'api_key_env',
        'default_model',
        'available_models',
        'is_default',
        'is_active',
        'config',
    ];

    protected $casts = [
        'available_models' => 'array',
        'is_default'       => 'boolean',
        'is_active'        => 'boolean',
        'config'           => 'array',
    ];

    /** Retrieve the resolved API key from the environment. */
    public function resolveApiKey(): ?string
    {
        if (empty($this->api_key_env)) {
            return null;
        }
        return env($this->api_key_env);
    }
}
