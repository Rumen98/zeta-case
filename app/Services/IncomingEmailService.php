<?php

namespace App\Services;

use App\Models\AiEvaluation;
use App\Models\IncomingEmail;
use App\Models\TaskDraft;
use App\Services\Ai\Contracts\EmailParserInterface;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Exceptions\DuplicateEmailException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class IncomingEmailService
{
    // Columns the parser is allowed to write into task_drafts.
    // Anything outside this list (status, FKs, timestamps) is off-limits.
    private const PARSER_OUTPUT_WHITELIST = [
        'type', 'title', 'summary', 'priority',
        'suggested_project', 'suggested_team', 'confidence',
        'missing_information', 'suggested_next_action',
    ];

    public function __construct(
        private EmailParserInterface $parser,
        private AuditLogger $audit,
    ) {}

    public function ingest(array $payload): TaskDraft
    {
        $hash = $this->hash($payload);

        // Pre-flight dedup. The unique index on message_hash is the real
        // guarantee; this just lets us return a friendlier 409.
        if ($existing = IncomingEmail::where('message_hash', $hash)->first()) {
            throw new DuplicateEmailException($existing->id);
        }

        return DB::transaction(function () use ($payload, $hash) {
            try {
                $email = IncomingEmail::create([
                    'message_hash' => $hash,
                    'from_address' => $payload['from'],
                    'subject'      => $payload['subject'],
                    'body'         => $payload['body'],
                    'raw_payload'  => $payload,
                    'received_at'  => now(),
                ]);
            } catch (QueryException $e) {
                // Race with another in-flight ingest of the same email.
                if ($dup = IncomingEmail::where('message_hash', $hash)->first()) {
                    throw new DuplicateEmailException($dup->id);
                }
                throw $e;
            }

            $this->audit->log($email, 'received', ['from' => $email->from_address]);

            $evaluation = $this->evaluate($email);

            return $this->buildDraft($email, $evaluation);
        });
    }

    private function evaluate(IncomingEmail $email): AiEvaluation
    {
        try {
            $result = $this->parser->parse($email);
        } catch (AiProviderException $e) {
            $this->audit->log($email, 'evaluation_failed', ['error' => $e->getMessage()]);

            return AiEvaluation::create([
                'incoming_email_id' => $email->id,
                'provider'          => $this->parser->providerName(),
                'status'            => AiEvaluation::STATUS_FAILED,
                'error_message'     => $e->getMessage(),
                'evaluated_at'      => now(),
            ]);
        }

        $evaluation = AiEvaluation::create([
            'incoming_email_id' => $email->id,
            'provider'          => $this->parser->providerName(),
            'model'             => $result->model,
            'status'            => AiEvaluation::STATUS_SUCCESS,
            'output'            => $result->toDraftAttributes() + ['raw' => $result->rawOutput],
            'latency_ms'        => $result->latencyMs,
            'evaluated_at'      => now(),
        ]);

        $this->audit->log($email, 'evaluated', [
            'provider'   => $this->parser->providerName(),
            'confidence' => $result->confidence,
        ]);

        return $evaluation;
    }

    private function buildDraft(IncomingEmail $email, AiEvaluation $evaluation): TaskDraft
    {
        $attrs = $evaluation->succeeded()
            ? array_intersect_key($evaluation->output ?? [], array_flip(self::PARSER_OUTPUT_WHITELIST))
            : [];

        $attrs['incoming_email_id'] = $email->id;
        $attrs['ai_evaluation_id']  = $evaluation->id;
        $attrs['status']            = $evaluation->succeeded()
            ? TaskDraft::STATUS_PENDING
            : TaskDraft::STATUS_NEEDS_MANUAL_REVIEW;

        return TaskDraft::create($attrs);
    }

    private function hash(array $p): string
    {
        return hash('sha256', ($p['from'] ?? '').'|'.($p['subject'] ?? '').'|'.($p['body'] ?? ''));
    }
}
