<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'context',
        'actor',
        'occurred_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'occurred_at' => 'datetime',
    ];
}
