<?php

declare(strict_types=1);

namespace App\Models;

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
        'risk_level',
        'requires_confirmation',
        'is_active',
    ];

    protected $casts = [
        'type'                  => ToolType::class,
        'risk_level'            => RiskLevel::class,
        'config'                => 'array',
        'requires_confirmation' => 'boolean',
        'is_active'             => 'boolean',
    ];
}
