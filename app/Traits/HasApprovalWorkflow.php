<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ApprovalStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared trait for models that participate in approval workflows.
 *
 * @property ApprovalStatus $status
 * @property string|null $reviewer_id
 */
trait HasApprovalWorkflow
{
    public function isEditable(): bool
    {
        return in_array($this->status, [
            ApprovalStatus::Draft,
            ApprovalStatus::ChangesRequested,
            ApprovalStatus::Withdrawn,
        ], true);
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
