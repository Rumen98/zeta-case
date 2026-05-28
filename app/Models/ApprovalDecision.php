<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalDecision extends Model
{
    use HasFactory;

    const DECISION_APPROVED   = 'approved';
    const DECISION_REJECTED   = 'rejected';
    const DECISION_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'task_draft_id',
        'operator_identifier',
        'decision',
        'note',
        'overridden_fields',
        'decided_at',
    ];

    protected $casts = [
        'overridden_fields' => 'array',
        'decided_at'        => 'datetime',
    ];

    public function taskDraft()
    {
        return $this->belongsTo(TaskDraft::class);
    }
}
