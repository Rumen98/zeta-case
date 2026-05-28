<?php

namespace App\Services\Exceptions;

use RuntimeException;

class DraftAlreadyDecidedException extends RuntimeException
{
    public function __construct(public readonly int $draftId, public readonly string $currentStatus)
    {
        parent::__construct("Task draft {$draftId} is already in status '{$currentStatus}'.");
    }
}
