<?php

namespace Tests\Feature;

use App\Models\TaskDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailToTaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingesting_an_email_creates_a_pending_task_draft(): void
    {
        $response = $this->postJson('/api/incoming-emails', [
            'from'    => 'alice@example.com',
            'subject' => 'Bug: dashboard crashes on load',
            'body'    => 'Steps to reproduce: open dashboard, click Reports. The page crashes immediately. Expected: it should load.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', TaskDraft::STATUS_PENDING)
            ->assertJsonPath('data.type', 'bug')
            ->assertJsonPath('data.ai_evaluation.status', 'success');
    }

    public function test_duplicate_email_is_rejected_with_409(): void
    {
        $payload = [
            'from'    => 'alice@example.com',
            'subject' => 'Hello',
            'body'    => 'This is a long enough body for validation.',
        ];

        $this->postJson('/api/incoming-emails', $payload)->assertCreated();
        $this->postJson('/api/incoming-emails', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'duplicate_email');
    }

    public function test_validation_fails_when_body_too_short(): void
    {
        $this->postJson('/api/incoming-emails', [
            'from'    => 'alice@example.com',
            'subject' => 'Hi',
            'body'    => 'short',
        ])->assertStatus(422)->assertJsonValidationErrorFor('body');
    }

    public function test_ai_failure_results_in_needs_manual_review_status(): void
    {
        $response = $this->postJson('/api/incoming-emails', [
            'from'    => 'alice@example.com',
            'subject' => '[SIMULATE_FAIL] something went wrong',
            'body'    => 'This body is long enough to pass validation.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', TaskDraft::STATUS_NEEDS_MANUAL_REVIEW)
            ->assertJsonPath('data.ai_evaluation.status', 'failed');
    }

    public function test_approving_a_draft_moves_it_to_approved(): void
    {
        $draft = $this->ingestSample();

        $response = $this->postJson("/api/task-drafts/{$draft->id}/approve", [
            'operator' => 'pm@unity.test',
            'note'     => 'Looks good, creating in ClickUp.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', TaskDraft::STATUS_APPROVED);
    }

    public function test_a_draft_cannot_be_approved_twice(): void
    {
        $draft = $this->ingestSample();

        $this->postJson("/api/task-drafts/{$draft->id}/approve", [
            'operator' => 'pm@unity.test',
        ])->assertOk();

        $this->postJson("/api/task-drafts/{$draft->id}/approve", [
            'operator' => 'pm@unity.test',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_decided');
    }

    public function test_override_without_reason_is_rejected(): void
    {
        $draft = $this->ingestSample();

        $this->postJson("/api/task-drafts/{$draft->id}/override", [
            'operator' => 'pm@unity.test',
            'changes'  => ['priority' => 'urgent'],
        ])->assertStatus(422)->assertJsonValidationErrorFor('reason');
    }

    public function test_override_records_diff_and_updates_draft(): void
    {
        $draft = $this->ingestSample();

        $response = $this->postJson("/api/task-drafts/{$draft->id}/override", [
            'operator' => 'pm@unity.test',
            'reason'   => 'Customer is on enterprise plan — bumping priority.',
            'changes'  => ['priority' => 'urgent', 'suggested_team' => 'engineering'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', TaskDraft::STATUS_OVERRIDDEN)
            ->assertJsonPath('data.priority', 'urgent');
    }

    private function ingestSample(): TaskDraft
    {
        $this->postJson('/api/incoming-emails', [
            'from'    => 'alice@example.com',
            'subject' => 'Bug: dashboard crashes',
            'body'    => 'Steps: open dashboard, click Reports. Expected: loads. Actual: crash.',
        ])->assertCreated();

        return TaskDraft::query()->latest('id')->firstOrFail();
    }
}
