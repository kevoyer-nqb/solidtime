<?php

declare(strict_types=1);

namespace App\Enums;

enum ApprovalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
