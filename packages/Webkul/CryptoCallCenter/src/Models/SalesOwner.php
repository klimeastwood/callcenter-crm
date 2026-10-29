<?php

namespace Webkul\CryptoCallCenter\Models;

use Illuminate\Database\Eloquent\Builder;
use Webkul\CryptoCallCenter\Support\TradingClients;
use Webkul\User\Models\User;

/**
 * A staff account that may own a lead.
 *
 * The users table is shared with the trading cabinet, where a self-registered
 * client is a CRM user too. This model is the same table seen through the staff
 * lens: a global scope drops the accounts carrying a client role, so the "Sales
 * Owner" selector of the lead form and the owner filter of the lead list cannot
 * offer a customer as if it were a manager.
 *
 * It exists as a separate class instead of a change to `Webkul\User\Models\User`
 * because the client must stay visible everywhere else - Settings → Users, the
 * lead card, the trading feed - and only the owner lookup needs the narrower
 * list.
 */
class SalesOwner extends User
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Drop the trading clients from every query of this model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('staff-only', function (Builder $builder): void {
            TradingClients::filter($builder);
        });
    }
}
