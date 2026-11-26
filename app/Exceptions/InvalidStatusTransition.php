<?php

namespace App\Exceptions;

use App\Enums\ApplicationStatus;
use RuntimeException;

class InvalidStatusTransition extends RuntimeException
{
    public function __construct(ApplicationStatus $from, ApplicationStatus $to)
    {
        parent::__construct("An application cannot move from {$from->label()} to {$to->label()}.");
    }
}
