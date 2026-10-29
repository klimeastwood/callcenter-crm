<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\DepositRequest as DepositRequestContract;
use Webkul\User\Models\UserProxy;

/**
 * A deposit the client staged in the trading cabinet.
 *
 * The row belongs to the trading application and creating one does not move
 * money: the client names the card the money came from, the operator checks the
 * transfer and only then approves it. The approval credits the balance through
 * the trading bridge and is recorded here (`status`, `is_approved`,
 * `reviewed_by`, `reviewed_at`, `review_note`), so the queue and the client
 * cabinet tell the same story.
 */
class DepositRequest extends Model implements DepositRequestContract
{
    /**
     * Waiting for the operator.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Confirmed and credited.
     */
    public const STATUS_APPROVED = 'approved';

    /**
     * Refused, nothing was credited.
     */
    public const STATUS_REJECTED = 'rejected';

    /**
     * The statuses the trading application writes.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'deposit_card_id',
        'amount_usd',
        'status',
        'is_approved',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'deposit_card_id' => 'integer',
        'amount_usd' => 'decimal:8',
        'is_approved' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the client who requested the deposit.
     *
     * The trading cabinets and the CRM share one `users` table, which is how the
     * CRM already links a trading event to a lead owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    /**
     * Get the card the money came from.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(DepositCard::class, 'deposit_card_id');
    }

    /**
     * Get the employee who reviewed the request, when one did.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'reviewed_by');
    }

    /**
     * A request nobody has decided yet.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * The status in the panel language.
     */
    public function statusLabel(): string
    {
        $key = 'crypto_call_center::app.deposits.status.'.$this->status;

        $label = trans($key);

        return $label === $key ? (string) $this->status : $label;
    }

    /**
     * The number the money came from, decrypted for the review screen only.
     */
    public function pan(): ?string
    {
        return $this->card?->pan();
    }
}
