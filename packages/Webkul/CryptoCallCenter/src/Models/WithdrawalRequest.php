<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\WithdrawalRequest as WithdrawalRequestContract;
use Webkul\User\Models\UserProxy;

/**
 * A payout the client requested in the trading cabinet.
 *
 * Creating the row does not move money: the client stages the request, the
 * cabinet shows it as pending, and the payout desk pays it outside both
 * applications. The row itself belongs to the trading application, which is why
 * the decision is written through the bridge (or into the shared table when the
 * database is the declared transport) and never by touching the status from a
 * controller: the trading side stays the single owner of the record.
 */
class WithdrawalRequest extends Model implements WithdrawalRequestContract
{
    /**
     * Waiting for the payout desk.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Approved, not paid yet.
     */
    public const STATUS_APPROVED = 'approved';

    /**
     * Refused by the payout desk.
     */
    public const STATUS_REJECTED = 'rejected';

    /**
     * Money has left the account.
     */
    public const STATUS_PAID = 'paid';

    /**
     * Payout to a bank card.
     */
    public const METHOD_CARD = 'card';

    /**
     * Payout to a crypto wallet.
     */
    public const METHOD_CRYPTO = 'crypto';

    /**
     * The statuses the trading application writes.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
    ];

    /**
     * The methods the trading application accepts.
     *
     * @var array<int, string>
     */
    public const METHODS = [
        self::METHOD_CARD,
        self::METHOD_CRYPTO,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'withdrawal_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'withdrawal_card_id',
        'amount_usd',
        'method',
        'wallet_address',
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
        'withdrawal_card_id' => 'integer',
        'amount_usd' => 'decimal:8',
        'is_approved' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the client who requested the payout.
     *
     * The trading cabinets and the CRM share one `users` table, which is how the
     * CRM already links a trading event to a lead owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    /**
     * Get the card the payout should be sent to.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(WithdrawalCard::class, 'withdrawal_card_id');
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
     *
     * `approved` is not open for a decision: the request is closed, the client
     * already reads the answer in the cabinet, and the back office still has to
     * pay it. Only `pending` may be approved or rejected.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approved, but the money has not left the account yet: the only state the
     * payout desk may mark as completed.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * The payout is done. `paid` is the row the back office writes after the
     * money left the account; the panel calls it «Выплачено».
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * A request the client cannot repeat: it waits for the payout desk.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    /**
     * The status in the panel language.
     */
    public function statusLabel(): string
    {
        $key = 'crypto_call_center::app.withdrawals.status.'.$this->status;

        $label = trans($key);

        return $label === $key ? (string) $this->status : $label;
    }

    /**
     * The payout method in the panel language.
     */
    public function methodLabel(): string
    {
        $key = 'crypto_call_center::app.withdrawals.methods.'.$this->method;

        $label = trans($key);

        return $label === $key ? (string) $this->method : $label;
    }

    /**
     * The number to pay out, decrypted for the payout desk only.
     */
    public function pan(): ?string
    {
        return $this->card?->pan();
    }
}
