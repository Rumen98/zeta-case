# ZETA — Email-to-Task Assistant

Small Laravel feature that takes a raw email, asks an "AI" to draft a structured task from it, and hands the draft to a human PM for approval. The AI never gets to create a final task on its own — that's the whole point of the brief.

Built for the Perspective Unity 2–3h case study, so I optimised for clear seams and decent failure handling, not for feature count.

## Run it

Laravel 12 / PHP 8.3. Drop the files in, then:

```bash
php artisan migrate
php artisan test --filter=EmailToTaskFlowTest
php artisan serve
```

Service provider has to be registered in `bootstrap/providers.php`:

```php
App\Providers\AiServiceProvider::class,
```

And `bootstrap/app.php` needs the API route file enabled (no Sanctum, no `install:api` — just add the line):

```php
->withRouting(
    web:      __DIR__.'/../routes/web.php',
    api:      __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health:   '/up',
)
```

The default parser is the mock. To switch to OpenAI later:

```
AI_PARSER=openai
OPENAI_API_KEY=sk-...
```

(The OpenAI parser class is a stub — see "Things I didn't build" below.)

Quick curl to see it work:

```bash
curl -X POST http://127.0.0.1:8000/api/incoming-emails \
  -H 'Content-Type: application/json' \
  -d '{
    "from":"alice@acme.com",
    "subject":"Bug: dashboard crashes on load",
    "body":"Steps: open dashboard, click Reports. Expected: page loads. Actual: crash."
  }'
```

## Endpoints

| Method | Path                                  | What it does                          |
| ------ | ------------------------------------- | ------------------------------------- |
| POST   | `/api/incoming-emails`                | Ingest an email, return a draft       |
| GET    | `/api/task-drafts/{id}`               | Show a draft + evaluation + decisions |
| POST   | `/api/task-drafts/{id}/approve`       | Approve as-is                         |
| POST   | `/api/task-drafts/{id}/reject`        | Reject with reason                    |
| POST   | `/api/task-drafts/{id}/override`      | Edit fields with reason               |

## How the pieces fit together

```
POST /api/incoming-emails
        |
        v
IncomingEmailService::ingest
        |
        |-- hash(from|subject|body) — dedup, returns 409 on hit
        |
        |-- DB transaction:
        |     IncomingEmail::create
        |     AuditLog: 'received'
        |
        |     EmailParserInterface::parse
        |       success -> AiEvaluation(success)
        |       failure -> AiEvaluation(failed)  (the request still succeeds)
        |
        |     TaskDraft::create with status:
        |       pending             when AI worked
        |       needs_manual_review when AI threw
        v
        TaskDraft (the thing the operator reviews)
                |
                |-- POST .../approve   -> status=approved
                |-- POST .../reject    -> status=rejected (reason required)
                |-- POST .../override  -> status=overridden + per-field diff
```

The split that matters: **the parser produces data, the orchestration service decides what to do with it.** The parser doesn't touch the DB, doesn't pick a status, doesn't know what `pending` means. That's what makes swapping the implementation a one-class job.

## Data model

Five tables. I went wider rather than wider-and-fewer because each of these has a different access pattern.

```
incoming_emails ──┬── ai_evaluations    (1..N — keeps history of parser runs)
                  │
                  └── task_drafts       (1 per email)
                                     │
                                     └── approval_decisions (1..N — usually 1)

audit_logs (separate; records every named action)
```

- **`incoming_emails`** — raw input. `message_hash` is the dedup key: SHA-256 of `from|subject|body`, unique-indexed. `raw_payload` is kept so I can re-run the parser later without re-ingesting.
- **`ai_evaluations`** — one row per parser run, success or failure. I deliberately did *not* put the AI output as columns on `task_drafts`, because re-evaluating an email with a different model needs to be a plain insert and we want to keep the history.
- **`task_drafts`** — the reviewable artifact. Every AI-suggested column is nullable. Status is a string: `pending` / `approved` / `rejected` / `overridden` / `needs_manual_review`. I'd switch to a PHP enum if this grows.
- **`approval_decisions`** — one row per human action. The override diff lives here as JSON: `{"priority":{"from":"low","to":"high"}}`. Querying this is how you'd answer "how often does the AI get priority wrong?".
- **`audit_logs`** — append-only event stream. Separate from Eloquent's `updated_at` because I want named events (`received`, `evaluated`, `evaluation_failed`, `approved`, `rejected`, `overridden`), not generic "updated".

## AI abstraction

One interface:

```php
interface EmailParserInterface
{
    public function parse(IncomingEmail $email): ParsedEmailResult;
    public function providerName(): string;
}
```

Two implementations:

- `MockEmailParser` — keyword heuristics. Deterministic. Used by default so the whole API and the tests run without burning tokens. Supports `[SIMULATE_FAIL]` in the subject to force the failure branch.
- `OpenAiEmailParser` — stub. Throws by default. A real implementation would call the OpenAI HTTP API in JSON mode, validate the response against a JSON schema, then map it into `ParsedEmailResult`. I left the wiring in place to show the seam, but didn't build the HTTP call.

`AiServiceProvider` is the *only* place in the codebase that knows which implementation is active. Controllers and services depend on the interface.

`ParsedEmailResult` is a plain immutable DTO. Its shape matches the `task_drafts` columns 1:1, so `toDraftAttributes()` is the only translation layer between "AI" and "persistence".

Failures are signalled with one exception type: `AiProviderException`. `IncomingEmailService::evaluate` catches it, writes a failed `AiEvaluation` row, and lets the request still succeed — the operator then gets a `needs_manual_review` draft to act on. The whole point is that an AI hiccup is a normal event, not a 500.

## Human review flow

`TaskDraftReviewService` is the only thing allowed to flip a draft's status.

- **approve** — operator + optional note. Status -> `approved`.
- **reject**  — operator + reason (required). Status -> `rejected`.
- **override**— operator + reason + ≥1 valid change. Status -> `overridden`, diff stored.

Every transition:

1. Opens a transaction.
2. Re-fetches the draft with `lockForUpdate()` and checks `isReviewable()`. This is the only thing protecting against two operators clicking approve at the same instant.
3. Writes the `ApprovalDecision`.
4. Updates the draft.
5. Writes an `AuditLog` entry.

Override is the most paranoid path. Only six fields are editable (`type`, `title`, `summary`, `priority`, `suggested_project`, `suggested_team`); anything else is filtered out with `array_intersect_key` *before* the transaction even opens. Reason gets a `trim()`-then-empty check too, on top of FormRequest validation, because the brief explicitly listed "override without reason" as something to handle.

## Failure handling

| What goes wrong              | Where it's caught                                  | Result                                      |
| ---------------------------- | -------------------------------------------------- | ------------------------------------------- |
| Missing/short body, bad email| `StoreIncomingEmailRequest` validation             | 422 with field errors, no DB write          |
| Duplicate email              | unique index + pre-flight lookup + race fallback   | 409 with the existing `incoming_email_id`   |
| AI provider throws           | `IncomingEmailService::evaluate`                   | failed `AiEvaluation`, draft = `needs_manual_review`, request returns 201 |
| Approved/decided twice       | `lockForUpdate()` + `isReviewable()` inside review tx | 422 `already_decided` + current status   |
| Override without reason      | FormRequest + service-level guard (belt and braces)| 422 (either validation or `invalid_override`)|
| Override with no editable fields | service-level whitelist check                  | 422 `invalid_override`                      |
| Email too vague              | not a failure — surfaced as data                   | low `confidence`, populated `missing_information`, `suggested_next_action` like "Reply to sender for clarification" |

The last one is intentional. "Vague" is information, not a system failure. The point is to flag the ambiguity to the human, not to invent details.

## What I traded off

- **No auth.** Brief doesn't require it. `operator_identifier` is a string. In real life it'd be `auth()->id()` against a `users` table.
- **Synchronous AI call.** A real LLM call belongs in a queue with retries. I left it inline because the brief allows mocking and queue infra is a lot of code for a case study.
- **No retry/backoff on AI failures.** Today a failed parse goes straight to `needs_manual_review`. Production would queue retries for transient errors first.
- **No outbound integration.** Approval doesn't actually create a task in ClickUp/Jira/etc. See ANSWERS.md for how I'd wire that.
- **Status / type / priority are strings.** Should be enums. Skipped for speed.
- **One feature test file.** Hits the happy path and each failure branch. I'd add unit tests for the parser and review service next.

## Things I didn't build (on purpose)

- Queue + broadcast (for "AI is still thinking" UX)
- Re-evaluate endpoint
- Outbound `TaskSinkInterface` (the ClickUp side)
- Operator notifications
- `Idempotency-Key` header support
- Real OpenAI HTTP call
- Admin UI

Each is an additive change. The schema already supports multiple evaluations per email, drafts that linger in `evaluating` state, sink result columns, and so on. ANSWERS.md goes into more detail.

## File map

```
app/
├── Http/
│   ├── Controllers/Api/{IncomingEmail,TaskDraft}Controller.php
│   ├── Requests/{Store,Approve,Reject,Override}*Request.php
│   └── Resources/TaskDraftResource.php
├── Models/{IncomingEmail,AiEvaluation,TaskDraft,ApprovalDecision,AuditLog}.php
├── Providers/AiServiceProvider.php
└── Services/
    ├── Ai/
    │   ├── Contracts/EmailParserInterface.php
    │   ├── Data/ParsedEmailResult.php
    │   ├── Exceptions/AiProviderException.php
    │   ├── MockEmailParser.php
    │   └── OpenAiEmailParser.php  (stub)
    ├── Exceptions/{DuplicateEmail,DraftAlreadyDecided,InvalidOverride}Exception.php
    ├── AuditLogger.php
    ├── IncomingEmailService.php
    └── TaskDraftReviewService.php

database/migrations/         5 files
routes/api.php
tests/Feature/EmailToTaskFlowTest.php
```
