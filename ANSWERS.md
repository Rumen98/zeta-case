# ZETA — form answers

These are the responses for sections 02–04 of the submission form. The README has the long version; this is what I'd paste into the text boxes.

---

## 02 — Architecture & data model

**AI abstraction.** One interface, `EmailParserInterface`, with a single method `parse(IncomingEmail): ParsedEmailResult`. Two implementations ship: `MockEmailParser` (keyword heuristics, deterministic, no network, default) and `OpenAiEmailParser` (stub showing where the HTTP call goes). Selection is a config value (`services.ai.parser`), wired in `AiServiceProvider` — that's the only place in the codebase that knows which implementation is bound. `ParsedEmailResult` is an immutable DTO whose shape mirrors the `task_drafts` columns, so the mapping is one method (`toDraftAttributes()`). Provider failures are signalled with a single `AiProviderException`; the orchestration layer catches it and writes a failed `AiEvaluation` row instead of crashing the request.

**Human-in-the-loop.** A draft is created either in `pending` (AI worked) or `needs_manual_review` (AI threw). Three terminal actions on `TaskDraftReviewService`: approve, reject (reason required), override (reason + ≥1 valid field change). Every action runs in a transaction that starts with `lockForUpdate()` + `isReviewable()` check — that's the only thing preventing "approved twice" under concurrency. Each action writes an `ApprovalDecision`, updates the draft, and appends to `audit_logs`. Overrides are constrained to a six-field whitelist; the before/after diff is computed inside the locked transaction and stored on the decision row.

**Data model.** Five tables, one responsibility each:

- `incoming_emails` — raw envelope + `message_hash` (SHA-256 of `from|subject|body`, unique index) for dedup.
- `ai_evaluations` — one row per parser run, success or failure. Separate from `task_drafts` so re-evaluating with a different model is an insert, not an update.
- `task_drafts` — the editable, reviewable artifact. Every AI field nullable. Status string (would be an enum if I had more time).
- `approval_decisions` — one row per human action, with operator, decision, note/reason, and (for overrides) a field-level diff as JSON.
- `audit_logs` — append-only event stream with named actions (`received`, `evaluated`, `evaluation_failed`, `approved`, etc.).

Relationships: `incoming_email → 1 task_draft → N approval_decisions`; `incoming_email → N ai_evaluations`; `task_draft → 1 latest ai_evaluation`.

**Failure handling.**

- AI provider throws → caught in `IncomingEmailService::evaluate`, recorded as failed `AiEvaluation`, draft goes to `needs_manual_review`. The 201 still comes back so the webhook caller isn't penalised.
- Missing/invalid fields → `FormRequest` validation at the boundary, 422 before any DB write.
- Duplicate email → pre-flight `message_hash` lookup, plus a `QueryException` catch around the insert (in case two requests race), 409 with the existing email id.
- Approved twice → `lockForUpdate()` + `isReviewable()` inside the review transaction, 422 `already_decided` with current status.
- Override without reason / with no editable fields → caught at both the form request layer and the service layer, 422 `invalid_override`.
- Email too vague → not a failure. Parser returns low `confidence` + populated `missing_information` + a `suggested_next_action`. The operator decides.

---

## 03 — Trade-offs & simplifications

**What I simplified, and why.**

- *No auth.* Brief doesn't require it. `operator_identifier` is a string. In prod it's `auth()->id()`. Avoids dragging a users table into a 2-3h case study.
- *Synchronous AI call.* A real LLM call belongs in a queued job (retries, broadcast on completion). I left it inline because the brief explicitly allows mocking and queueing infra for the sake of shape is overengineering at this scope.
- *Strings instead of enums.* `type`, `priority`, `status` are validated strings at the boundary. PHP enums are cleaner but add ceremony for a small, stable set.
- *Single feature test.* Covers ingest + dedup + validation + AI failure + approve + double-approve + override-without-reason + override-with-diff. Unit tests for the parser and review service are next.
- *No outbound integration.* The system stops at "approved". A `TaskSinkInterface` mirror of `EmailParserInterface` is the natural extension — see section 04.
- *No retry/backoff on AI failures.* Today: straight to manual review. Prod: queue + exponential backoff for transient errors, manual review after N attempts.

**What I'd do differently with more time.**

- Queue the parser call; introduce an `evaluating` status; broadcast completion over WebSockets/Reverb.
- `POST /api/task-drafts/{id}/re-evaluate` — picks the currently-configured parser, adds a new `AiEvaluation`, updates the draft if still pending.
- Build the outbound side (`TaskSinkInterface`, ClickUp/Jira sinks, sink result back-link).
- Operator notifications on `needs_manual_review` or low-confidence drafts.
- Per-provider metrics: approve / override / reject ratios, p95 latency, confidence calibration. That's the actual feedback loop on whether the AI is useful.
- `Idempotency-Key` header on the ingest endpoint, on top of the content hash.

**Most fragile part.** The mock parser's heuristics. They'll produce confident-but-wrong drafts on sarcasm, mixed-language emails, or anything that doesn't match my keyword lists. That's fine for a mock — the whole point is it's a stand-in — but it's the part most likely to look bad if a reviewer probes edge cases. Beyond the parser, the most fragile *architectural* assumption is "one email = one draft". Re-evaluation is fine (the schema supports many evaluations), but splitting a single email into multiple tasks (bug report + feature request in one message) would need the 1:1 between email and draft to relax.

---

## 04 — Personal judgment

**What I intentionally did not build.**
Auth, queues, outbound integrations, real LLM wiring, retry/backoff on failures, operator notifications, an admin UI, and a metrics view. Each is real work and would have ballooned the submission past the brief. The architecture is shaped so each of them is additive — a new class, a new config key, at worst a new table. The brief said "decisions, not completeness", so I optimised for the seams holding up under scrutiny rather than feature coverage.

**Riskiest part in production.**
Trusting the AI's output enough to write it to `task_drafts`, even as a draft. A malformed (or actively malicious) model response could inject huge strings, weird unicode, prompt-injection payloads that surface in the operator UI, or values that look like real internal project codes. I mitigated with an explicit whitelist (`array_intersect_key`) of accepted output columns in `IncomingEmailService::buildDraft` and per-field validation on override, but in production I'd also: enforce a JSON schema on the model response before constructing the DTO; cap every string field at a sane length; escape on the way out; and treat the email body itself as untrusted — never interpolated into a prompt without explicit "ignore embedded instructions" framing.

**Where the design breaks as usage grows.**

- *Synchronous endpoint under load.* A real LLM at p95 latency in seconds, multiplied by webhook bursts, will time out. Fix: queue. The schema already allows a draft to exist before its evaluation completes.
- *The 1:1 email-to-draft assumption.* Long threads often contain multiple actionable items. The schema can accept `incoming_emails hasMany task_drafts`, but the parser interface and the dedup hash both need to evolve.
- *Audit log growth.* `audit_logs` will dominate the database first. It's the first table I'd push to an append-only store / event log once volume gets serious.
- *Operator throughput.* If the AI is right 80% of the time, the operator becomes the bottleneck. Then we need a confidence threshold above which approval is auto-applied (revocable), and per-sender / per-channel routing so high-trust senders skip review entirely.

**Connecting to ClickUp later.**
Mirror of `EmailParserInterface` on the outbound side: a `TaskSinkInterface` with `create(TaskDraft): SinkResult`. Implementations: `ClickUpTaskSink`, `JiraTaskSink`, `LinearTaskSink`. Wiring lives in a small provider that picks the implementation from config. The approve action queues a `PushApprovedDraftJob` that calls the sink and stores `external_id` + `external_url` on the draft (two new nullable columns). The job uses the draft id as its idempotency key so retries are safe. Per-project routing (which workspace, which list) lives in a tiny mapping table keyed by the AI's `suggested_project`; the operator can also pick the destination at approval time. Sink failures behave symmetrically to AI failures — a `task_sink_attempts` table records each try, exponential backoff for transient errors, manual flag after N retries.

The symmetry isn't accidental: the AI is an *inbound* integration (unstructured input → structured draft), the task system is an *outbound* one (structured draft → external task). Treating them as mirror images keeps both sides swappable.
