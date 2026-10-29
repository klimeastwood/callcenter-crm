<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\BalanceAdjustment as BalanceAdjustmentContract;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\UserProxy;

/**
 * One manual balance change made by a CRM operator.
 *
 * The row is an audit trail, not the balance itself: the trading application
 * owns `trading_accounts.balance_usd`. `balance_before` and `balance_after`
 * are copied from the trading side, so the history of a lead stays readable
 * even when the trading database is not reachable.
 */
class BalanceAdjustment extends Model implements BalanceAdjustmentContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crypto_balance_adjustments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'admin_id',
        'amount',
        'balance_before',
        'balance_after',
        'trading_event_id',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'lead_id' => 'integer',
        'user_id' => 'integer',
        'admin_id' => 'integer',
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'trading_event_id' => 'integer',
    ];

    /**
     * The lead the adjustment belongs to.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    /**
     * The operator that made the change.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'admin_id');
    }
}
