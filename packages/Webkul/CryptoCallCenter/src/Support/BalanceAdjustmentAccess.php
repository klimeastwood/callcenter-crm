<?php

namespace Webkul\CryptoCallCenter\Support;

use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * Who may change a trader balance by hand.
 *
 * Four conditions, all of them required: the role grants the balance
 * permission, the lead has a trader account to change, and the account is
 * either the sales owner of that lead, an administrator, or a role declared as
 * a supervisor (`supervisor` in `Config/roles.php`) whose data scope already
 * covers the lead. Being able to open a lead is not enough - a group manager
 * reads the pipeline but does not move money.
 */
class BalanceAdjustmentAccess
{
    /**
     * The ACL key of the action, declared in `Config/acl.php`.
     */
    public const PERMISSION = 'crypto_call_center.balance';

    public static function allows(?User $user, ?Lead $lead): bool
    {
        if (! $user || ! $lead || ! $lead->user_id) {
            return false;
        }

        if (! bouncer()->hasPermission(self::PERMISSION)) {
            return false;
        }

        if ((int) $user->id === (int) $lead->user_id) {
            return true;
        }

        if (optional($user->role)->permission_type === 'all') {
            return true;
        }

        return OperatorScope::isSupervisor($user) && self::coveredByScope($user, $lead);
    }

    /**
     * Whether the lead is inside the data scope of the given account.
     *
     * The core filter (`bouncer()->getAuthorizedUserIds()`) is read off the
     * authenticated account, so the answer only means something when that
     * account is the one being asked about; anything else - a console command, a
     * queued job, another user's instance - falls back to "not covered", which
     * leaves the ownership rule as the only way in.
     */
    protected static function coveredByScope(User $user, Lead $lead): bool
    {
        $actor = auth()->guard('user')->user();

        if (! $actor || (int) $actor->id !== (int) $user->id) {
            return false;
        }

        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        if ($authorizedUserIds === null) {
            return true;
        }

        return in_array((int) $lead->user_id, array_map('intval', (array) $authorizedUserIds), true);
    }
}
