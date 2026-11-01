<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EncodeJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'status',
        'mode',
        'progress',
        'duration',
        'input_path',
        'meta',
        'outputs',
        'error',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'outputs' => 'array',
            'progress' => 'integer',
            'duration' => 'float',
            'expires_at' => 'datetime',
        ];
    }
}
