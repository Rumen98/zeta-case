<?php

namespace App\Services\Ai\Contracts;

use App\Models\IncomingEmail;
use App\Services\Ai\Data\ParsedEmailResult;
use App\Services\Ai\Exceptions\AiProviderException;

interface EmailParserInterface
{
    /** @throws AiProviderException on a non-recoverable provider failure. */
    public function parse(IncomingEmail $email): ParsedEmailResult;

    public function providerName(): string;
}
