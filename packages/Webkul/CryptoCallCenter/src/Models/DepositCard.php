<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Support\CardCipher;
use Webkul\User\Models\UserProxy;

/**
 * The card a client funded the account from, as the deposit desk reads it.
 *
 * The table belongs to the trading application; the CRM only reads it, and only
 * with the `deposits.review` permission. The ciphertext is hidden from
 * serialisation, so a stray `toArray()` - a debug dump, a future JSON endpoint -
 * cannot leak it either.
 */
class DepositCard extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_cards';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'holder_name',
        'brand',
        'last4',
        'expiry',
        'pan_encrypted',
        'fingerprint',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'pan_encrypted',
        'fingerprint',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * Get the user that owns the card.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    /**
     * The number every client screen shows: only the last four digits.
     */
    public function maskedNumber(): string
    {
        return trim('•••• •••• •••• '.$this->last4);
    }

    /**
     * The full number, for the review screen only.
     *
     * Null when this installation does not hold the key the row was sealed
     * with, so a misconfigured environment shows an explanation instead of a
     * broken string.
     */
    public function pan(): ?string
    {
        return CardCipher::decrypt($this->pan_encrypted);
    }

    /**
     * The opened number in groups of four, ready to match against a statement.
     */
    public function formattedPan(): ?string
    {
        $pan = $this->pan();

        return $pan === null ? null : implode(' ', str_split($pan, 4));
    }

    /**
     * Human readable payment system.
     */
    public function brandLabel(): string
    {
        $key = 'crypto_call_center::app.deposits.brands.'.($this->brand ?: 'unknown');

        $label = trans($key);

        return $label === $key
            ? trans('crypto_call_center::app.deposits.brands.unknown')
            : $label;
    }
}
