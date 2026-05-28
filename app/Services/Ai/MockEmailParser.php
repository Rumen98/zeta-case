<?php

namespace App\Services\Ai;

use App\Models\IncomingEmail;
use App\Services\Ai\Contracts\EmailParserInterface;
use App\Services\Ai\Data\ParsedEmailResult;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Support\Str;

// Cheap keyword-based stand-in for the real LLM so the rest of the flow
// can be developed and tested without a network call. Subject containing
// "[SIMULATE_FAIL]" forces the failure branch.
class MockEmailParser implements EmailParserInterface
{
    public function parse(IncomingEmail $email): ParsedEmailResult
    {
        $started = microtime(true);

        if (str_contains($email->subject, '[SIMULATE_FAIL]')) {
            throw new AiProviderException('Simulated provider failure (mock).');
        }

        $text = Str::lower($email->subject.' '.$email->body);

        $type     = $this->classifyType($text);
        $priority = $this->classifyPriority($text);
        $missing  = $this->findMissingInfo($email, $type);

        $confidence = max(0.05, min(0.99, round(0.9 - count($missing) * 0.15 - (strlen($email->body) < 50 ? 0.2 : 0), 2)));

        return new ParsedEmailResult(
            type:                $type,
            title:               Str::limit(trim($email->subject), 120, ''),
            summary:             Str::limit(trim(preg_replace('/\s+/', ' ', $email->body)), 280),
            priority:            $priority,
            suggestedProject:    $this->guessProject($email),
            suggestedTeam:       $this->routeTeam($type),
            confidence:          $confidence,
            missingInformation:  $missing,
            suggestedNextAction: $this->nextAction($confidence, $missing),
            rawOutput:           ['heuristic_version' => 'mock-v1'],
            latencyMs:           (int) ((microtime(true) - $started) * 1000),
            model:               'mock-heuristic-v1',
        );
    }

    public function providerName(): string
    {
        return 'mock';
    }

    private function classifyType(string $text): string
    {
        return match (true) {
            Str::contains($text, ['bug', 'error', 'broken', 'crash', 'fails', 'not working']) => 'bug',
            Str::contains($text, ['feature', 'add ', 'please add', 'would be nice', 'request']) => 'feature',
            Str::contains($text, ['how do', 'how can', 'why does', 'question', '?']) => 'question',
            default => 'unclear',
        };
    }

    private function classifyPriority(string $text): string
    {
        return match (true) {
            Str::contains($text, ['urgent', 'asap', 'critical', 'production down', 'blocker']) => 'urgent',
            Str::contains($text, ['important', 'soon', 'high priority']) => 'high',
            Str::contains($text, ['whenever', 'no rush', 'low priority', 'minor']) => 'low',
            default => 'medium',
        };
    }

    private function findMissingInfo(IncomingEmail $email, string $type): array
    {
        $body = Str::lower($email->body);
        $missing = [];

        if ($type === 'bug') {
            if (! Str::contains($body, ['step', 'reproduce', 'when i', 'after i'])) {
                $missing[] = 'reproduction_steps';
            }
            if (! Str::contains($body, ['expected', 'should'])) {
                $missing[] = 'expected_behavior';
            }
        }

        if ($type === 'feature' && strlen($email->body) < 80) {
            $missing[] = 'use_case_or_motivation';
        }

        if ($type === 'unclear') {
            $missing[] = 'intent_clarification';
        }

        return $missing;
    }

    private function guessProject(IncomingEmail $email): ?string
    {
        $domain = Str::after($email->from_address, '@');

        return $domain === '' ? null : 'project:'.Str::before($domain, '.');
    }

    private function routeTeam(string $type): string
    {
        return match ($type) {
            'bug'      => 'engineering',
            'feature'  => 'product',
            'question' => 'support',
            default    => 'triage',
        };
    }

    private function nextAction(float $confidence, array $missing): string
    {
        if ($confidence < 0.4) {
            return 'Reply to sender for clarification before creating a task.';
        }
        if ($missing) {
            return 'Ask sender for: '.implode(', ', $missing);
        }

        return 'Approve as-is and create the task.';
    }
}
