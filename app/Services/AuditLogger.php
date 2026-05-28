<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function log(Model $subject, string $action, array $context = [], ?string $actor = null): AuditLog
    {
        return AuditLog::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id'   => $subject->getKey(),
            'action'       => $action,
            'context'      => $context,
            'actor'        => $actor ?? 'system',
            'occurred_at'  => now(),
        ]);
    }
}
