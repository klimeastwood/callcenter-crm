<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\CallLog as CallLogContract;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\UserProxy;

class CallLog extends Model implements CallLogContract
{
    /**
     * The call went through and both sides talked.
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * The lead never picked up.
     */
    public const STATUS_MISSED = 'missed';

    /**
     * Telephony rejected or dropped the call before it was connected.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Statuses a telephony integration is expected to report. Kept in one
     * place so the column, the filter dropdown and the badge view agree.
     */
    public const STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_MISSED,
        self::STATUS_FAILED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'phone_number',
        'duration',
        'status',
        'recording_url',
        'transcript',
        'call_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'lead_id' => 'integer',
        'user_id' => 'integer',
        'duration' => 'integer',
        'call_date' => 'datetime',
    ];

    /**
     * The lead the call was made for.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    /**
     * The manager who handled the call. Null once the account is removed.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    /**
     * Human readable talk time: `m:ss`, or `h:mm:ss` past an hour.
     */
    public static function formatDuration(int|string|null $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        return $seconds >= 3600
            ? gmdate('G:i:s', $seconds)
            : gmdate('i:s', $seconds);
    }
}
