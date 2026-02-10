<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $member_id
 * @property string $notification_type
 * @property bool $email_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member $member
 *
 * @method static NotificationPreferenceFactory factory()
 * @method static Builder<NotificationPreference> query()
 */
class NotificationPreference extends Model implements AuditableContract
{
    use CustomAuditable;

    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'member_id',
        'notification_type',
        'email_enabled',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_enabled' => 'boolean',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @param  Builder<NotificationPreference>  $query
     * @return Builder<NotificationPreference>
     */
    public function scopeForMember(Builder $query, string $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * @param  Builder<NotificationPreference>  $query
     * @return Builder<NotificationPreference>
     */
    public function scopeForType(Builder $query, NotificationType $type): Builder
    {
        return $query->where('notification_type', $type->value);
    }
}
