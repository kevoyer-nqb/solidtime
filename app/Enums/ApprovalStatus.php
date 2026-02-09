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

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Approved, self::ChangesRequested, self::Rejected, self::Withdrawn],
            self::ChangesRequested => [self::Submitted, self::Withdrawn],
            self::Approved => [],
            self::Rejected => [self::Draft],
            self::Withdrawn => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Withdrawn], true);
    }
}
