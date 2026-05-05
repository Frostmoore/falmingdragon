<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RiskCategory;
use App\Enums\RiskLevel;
use App\Enums\ToolType;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'type',
        'handler_class',
        'config',
        'config_keys',
        'input_schema',
        'risk_level',
        'risk_category',
        'requires_confirmation',
        'is_active',
    ];

    protected $casts = [
        'type'                  => ToolType::class,
        'risk_level'            => RiskLevel::class,
        'risk_category'         => RiskCategory::class,
        'config'                => 'array',
        'config_keys'           => 'array',
        'input_schema'          => 'array',
        'requires_confirmation' => 'boolean',
        'is_active'             => 'boolean',
    ];

    /** PHP method name in ToolExecutor for this builtin tool. */
    public function executorMethod(): string
    {
        return 'run' . str_replace('_', '', ucwords($this->name, '_'));
    }
}
