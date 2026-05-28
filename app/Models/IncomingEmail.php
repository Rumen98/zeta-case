<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomingEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_hash',
        'from_address',
        'subject',
        'body',
        'raw_payload',
        'received_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
    ];

    public function aiEvaluations()
    {
        return $this->hasMany(AiEvaluation::class);
    }

    public function latestAiEvaluation()
    {
        return $this->hasOne(AiEvaluation::class)->latestOfMany();
    }

    public function taskDraft()
    {
        return $this->hasOne(TaskDraft::class);
    }
}
