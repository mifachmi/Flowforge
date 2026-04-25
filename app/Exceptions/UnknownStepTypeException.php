<?php

namespace App\Exceptions;

use RuntimeException;

class UnknownStepTypeException extends RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct("Unknown step type: [{$type}]");
    }
}
