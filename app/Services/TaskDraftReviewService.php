<?php

namespace App\Services;

use App\Models\ApprovalDecision;
use App\Models\TaskDraft;
use App\Services\Exceptions\DraftAlreadyDecidedException;
use App\Services\Exceptions\InvalidOverrideException;
use Illuminate\Support\Facades\DB;

class TaskDraftReviewService
{
    private const EDITABLE = [
        'type', 'title', 'summary', 'priority',
        'suggested_project', 'suggested_team',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function approve(TaskDraft $draft, string $operator, ?string $note = null): ApprovalDecision
    {
        return DB::transaction(function () use ($draft, $operator, $note) {
            $draft = $this->lockReviewable($draft);

            $decision = ApprovalDecision::create([
                'task_draft_id'       => $draft->id,
                'operator_identifier' => $operator,
                'decision'            => ApprovalDecision::DECISION_APPROVED,
                'note'                => $note,
                'decided_at'          => now(),
            ]);

            $draft->update(['status' => TaskDraft::STATUS_APPROVED]);
            $this->audit->log($draft, 'approved', ['note' => $note], $operator);

            return $decision;
        });
    }

    public function reject(TaskDraft $draft, string $operator, string $reason): ApprovalDecision
    {
        return DB::transaction(function () use ($draft, $operator, $reason) {
            $draft = $this->lockReviewable($draft);

            $decision = ApprovalDecision::create([
                'task_draft_id'       => $draft->id,
                'operator_identifier' => $operator,
                'decision'            => ApprovalDecision::DECISION_REJECTED,
                'note'                => $reason,
                'decided_at'          => now(),
            ]);

            $draft->update(['status' => TaskDraft::STATUS_REJECTED]);
            $this->audit->log($draft, 'rejected', ['reason' => $reason], $operator);

            return $decision;
        });
    }

    public function override(TaskDraft $draft, string $operator, array $changes, string $reason): ApprovalDecision
    {
        if (trim($reason) === '') {
            throw new InvalidOverrideException('Override requires a non-empty reason.');
        }

        $changes = array_intersect_key($changes, array_flip(self::EDITABLE));
        if (empty($changes)) {
            throw new InvalidOverrideException('No editable fields supplied.');
        }

        return DB::transaction(function () use ($draft, $operator, $changes, $reason) {
            $draft = $this->lockReviewable($draft);

            $diff = [];
            foreach ($changes as $field => $newValue) {
                $diff[$field] = ['from' => $draft->{$field}, 'to' => $newValue];
            }

            $decision = ApprovalDecision::create([
                'task_draft_id'       => $draft->id,
                'operator_identifier' => $operator,
                'decision'            => ApprovalDecision::DECISION_OVERRIDDEN,
                'note'                => $reason,
                'overridden_fields'   => $diff,
                'decided_at'          => now(),
            ]);

            $draft->update($changes + ['status' => TaskDraft::STATUS_OVERRIDDEN]);
            $this->audit->log($draft, 'overridden', ['diff' => $diff, 'reason' => $reason], $operator);

            return $decision;
        });
    }

    // Row-locks the draft inside the transaction so two operators can't both
    // approve at the same instant.
    private function lockReviewable(TaskDraft $draft): TaskDraft
    {
        $fresh = TaskDraft::lockForUpdate()->findOrFail($draft->id);

        if (! $fresh->isReviewable()) {
            throw new DraftAlreadyDecidedException($fresh->id, $fresh->status);
        }

        return $fresh;
    }
}
