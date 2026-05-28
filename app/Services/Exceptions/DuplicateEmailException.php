<?php

namespace App\Services\Exceptions;

use RuntimeException;

class DuplicateEmailException extends RuntimeException
{
    public function __construct(public readonly int $existingIncomingEmailId)
    {
        parent::__construct('This email was already ingested.');
    }
}
