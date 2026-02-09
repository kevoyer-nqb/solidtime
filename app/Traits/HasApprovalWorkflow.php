<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ApprovalStatus;
use App\Exceptions\InvalidApprovalTransitionException;
use App\Models\Member;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared trait for models that participate in approval workflows.
 * Enforces valid state transitions via model saving event.
 *
 * @property ApprovalStatus $status
 * @property string|null $reviewer_id
 */
trait HasApprovalWorkflow
{
    public static function bootHasApprovalWorkflow(): void
    {
        static::saving(function ($model): void {
            if ($model->isDirty('status') && $model->getOriginal('status') !== null) {
                $from = ApprovalStatus::from($model->getOriginal('status'));
                $to = $model->status;
                if (! $from->canTransitionTo($to)) {
                    throw new InvalidApprovalTransitionException($from, $to);
                }
            }
        });
    }

    public function transitionTo(ApprovalStatus $newStatus, ?Member $reviewer = null): void
    {
        if ($this->status !== null && ! $this->status->canTransitionTo($newStatus)) {
            throw new InvalidApprovalTransitionException($this->status, $newStatus);
        }

        $this->status = $newStatus;

        if ($reviewer !== null) {
            $this->reviewer_id = $reviewer->id;
        }
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isSubmitted(): bool
    {
        return $this->status === ApprovalStatus::Submitted;
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::Rejected;
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewer_id');
    }
}
