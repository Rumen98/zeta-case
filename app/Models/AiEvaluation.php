<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiEvaluation extends Model
{
    use HasFactory;

    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'incoming_email_id',
        'provider',
        'model',
        'status',
        'output',
        'error_message',
        'latency_ms',
        'evaluated_at',
    ];

    protected $casts = [
        'output'       => 'array',
        'evaluated_at' => 'datetime',
        'latency_ms'   => 'integer',
    ];

    public function incomingEmail()
    {
        return $this->belongsTo(IncomingEmail::class);
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
