<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDraft extends Model
{
    use HasFactory;

    const STATUS_PENDING             = 'pending';
    const STATUS_APPROVED            = 'approved';
    const STATUS_REJECTED            = 'rejected';
    const STATUS_OVERRIDDEN          = 'overridden';
    const STATUS_NEEDS_MANUAL_REVIEW = 'needs_manual_review';

    protected $fillable = [
        'incoming_email_id',
        'ai_evaluation_id',
        'type',
        'title',
        'summary',
        'priority',
        'suggested_project',
        'suggested_team',
        'confidence',
        'missing_information',
        'suggested_next_action',
        'status',
    ];

    protected $casts = [
        'missing_information' => 'array',
        'confidence'          => 'float',
    ];

    public function incomingEmail()
    {
        return $this->belongsTo(IncomingEmail::class);
    }

    public function aiEvaluation()
    {
        return $this->belongsTo(AiEvaluation::class);
    }

    public function approvalDecisions()
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_NEEDS_MANUAL_REVIEW], true);
    }
}
