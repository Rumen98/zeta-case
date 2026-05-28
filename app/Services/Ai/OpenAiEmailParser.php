<?php

namespace App\Services\Ai;

use App\Models\IncomingEmail;
use App\Services\Ai\Contracts\EmailParserInterface;
use App\Services\Ai\Data\ParsedEmailResult;
use App\Services\Ai\Exceptions\AiProviderException;

// Skeleton. Not wired by default — bind it via AI_PARSER=openai.
// A real implementation would call the OpenAI HTTP API with JSON mode,
// validate the response against a schema, then map it into ParsedEmailResult.
class OpenAiEmailParser implements EmailParserInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'gpt-4o-mini',
    ) {}

    public function parse(IncomingEmail $email): ParsedEmailResult
    {
        throw new AiProviderException('OpenAiEmailParser is not implemented in this submission.');
    }

    public function providerName(): string
    {
        return 'openai';
    }
}
