<?php

namespace App\Exceptions;

use RuntimeException;

class WorkflowStepException extends RuntimeException
{
    public function __construct(string $stepId, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Step [{$stepId}] failed: {$reason}", 0, $previous);
    }
}
