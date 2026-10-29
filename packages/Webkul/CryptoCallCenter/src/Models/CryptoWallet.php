<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\CryptoCallCenter\Contracts\CryptoWallet as CryptoWalletContract;
use Webkul\Lead\Models\LeadProxy;

class CryptoWallet extends Model implements CryptoWalletContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'lead_id',
        'wallet_address',
        'network',
    ];

    /**
     * The lead the wallet belongs to.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }
}
