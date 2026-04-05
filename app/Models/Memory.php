<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemoryType;
use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    protected $table = 'memory';

    protected $fillable = [
        'namespace',
        'key',
        'value',
        'memory_type',
        'source',
        'expires_at',
    ];

    protected $casts = [
        'memory_type' => MemoryType::class,
        'expires_at'  => 'datetime',
    ];

    /** Determine if this memory entry has expired. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
