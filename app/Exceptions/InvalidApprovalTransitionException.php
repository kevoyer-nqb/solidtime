<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ApprovalStatus;

class InvalidApprovalTransitionException extends \LogicException
{
    public function __construct(ApprovalStatus $from, ApprovalStatus $to)
    {
        parent::__construct(
            "Invalid approval transition from '{$from->value}' to '{$to->value}'."
        );
    }
}
