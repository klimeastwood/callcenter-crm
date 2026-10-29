<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\TradingEvent as TradingEventContract;
use Webkul\User\Models\UserProxy;

/**
 * A row of the event log the trading application writes.
 *
 * The table belongs to the trading application and the CRM only reads it, which
 * is why the model is thin: no timestamps of its own, no repository state, just
 * the columns the timeline needs. The `payload` column keeps the event specific
 * data (amounts, symbol, prices) as JSON.
 */
class TradingEvent extends Model implements TradingEventContract
{
    /**
     * Money was credited to the trading account.
     */
    public const TYPE_DEPOSIT = 'deposit';

    /**
     * A position was opened.
     */
    public const TYPE_POSITION_OPENED = 'position.opened';

    /**
     * A position was closed and the result was booked.
     */
    public const TYPE_POSITION_CLOSED = 'position.closed';

    /**
     * An operator credited or wrote off the balance by hand.
     */
    public const TYPE_BALANCE_ADJUSTMENT = 'balance.adjustment';

    /**
     * The client asked for a payout in the cabinet.
     */
    public const TYPE_WITHDRAWAL_REQUESTED = 'withdrawal.requested';

    /**
     * The client staged a deposit; an operator has to confirm it.
     *
     * The payload carries the amount, the masked card and the balance, so the
     * timeline shows what is waiting for the deposit desk without a number.
     */
    public const TYPE_DEPOSIT_REQUESTED = 'deposit.requested';

    /**
     * Event types this build can describe.
     *
     * An event of another type is still marked as processed, so that an older
     * CRM does not read the same unknown row on every run of the feed.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_DEPOSIT,
        self::TYPE_POSITION_OPENED,
        self::TYPE_POSITION_CLOSED,
        self::TYPE_BALANCE_ADJUSTMENT,
        self::TYPE_WITHDRAWAL_REQUESTED,
        self::TYPE_DEPOSIT_REQUESTED,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trading_events';

    /**
     * The trading application writes `created_at` itself and keeps no
     * `updated_at`, so Eloquent must not touch either column.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'event_type',
        'payload',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that owns the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }
}
