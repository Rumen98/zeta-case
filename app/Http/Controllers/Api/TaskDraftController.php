<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTaskDraftRequest;
use App\Http\Requests\OverrideTaskDraftRequest;
use App\Http\Requests\RejectTaskDraftRequest;
use App\Http\Resources\TaskDraftResource;
use App\Models\TaskDraft;
use App\Services\Exceptions\DraftAlreadyDecidedException;
use App\Services\Exceptions\InvalidOverrideException;
use App\Services\TaskDraftReviewService;

class TaskDraftController extends Controller
{
    public function __construct(private TaskDraftReviewService $review) {}

    public function show(TaskDraft $taskDraft)
    {
        return TaskDraftResource::make(
            $taskDraft->load(['incomingEmail', 'aiEvaluation', 'approvalDecisions'])
        );
    }

    public function approve(ApproveTaskDraftRequest $request, TaskDraft $taskDraft)
    {
        try {
            $this->review->approve($taskDraft, $request->validated('operator'), $request->validated('note'));
        } catch (DraftAlreadyDecidedException $e) {
            return $this->alreadyDecided($e);
        }

        return $this->refresh($taskDraft);
    }

    public function reject(RejectTaskDraftRequest $request, TaskDraft $taskDraft)
    {
        try {
            $this->review->reject($taskDraft, $request->validated('operator'), $request->validated('reason'));
        } catch (DraftAlreadyDecidedException $e) {
            return $this->alreadyDecided($e);
        }

        return $this->refresh($taskDraft);
    }

    public function override(OverrideTaskDraftRequest $request, TaskDraft $taskDraft)
    {
        try {
            $this->review->override(
                $taskDraft,
                $request->validated('operator'),
                $request->validated('changes'),
                $request->validated('reason'),
            );
        } catch (DraftAlreadyDecidedException $e) {
            return $this->alreadyDecided($e);
        } catch (InvalidOverrideException $e) {
            return response()->json(['error' => 'invalid_override', 'message' => $e->getMessage()], 422);
        }

        return $this->refresh($taskDraft);
    }

    private function refresh(TaskDraft $draft)
    {
        return TaskDraftResource::make(
            $draft->fresh(['incomingEmail', 'aiEvaluation', 'approvalDecisions'])
        )->response();
    }

    private function alreadyDecided(DraftAlreadyDecidedException $e)
    {
        return response()->json([
            'error'          => 'already_decided',
            'message'        => $e->getMessage(),
            'current_status' => $e->currentStatus,
        ], 422);
    }
}
