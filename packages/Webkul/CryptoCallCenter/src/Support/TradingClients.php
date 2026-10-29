<?php

namespace Webkul\CryptoCallCenter\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\User\Models\User;

/**
 * Who is a client of the trading cabinet, as opposed to call center staff.
 *
 * A self-registered trader is a CRM user like any other, and before this class
 * the "Sales Owner" selector offered every active account - including the
 * clients themselves, which let an operator hand a lead to a customer. The
 * trading application marks those accounts with the role it creates for them
 * ("Trading Client" by default, or an existing role pinned by id), so the role
 * is the fact this class reads.
 *
 * Both spellings are accepted because the trading side may create the role by
 * name or reuse one by id; `Config/trading.php` exposes them as
 * `crypto_call_center.trading.client_roles`.
 */
final class TradingClients
{
    /**
     * The role names the trading application gives a client.
     *
     * @return array<int, string>
     */
    public static function roleNames(): array
    {
        $names = config('crypto_call_center.trading.client_roles.names', []);

        if (! is_array($names)) {
            return [];
        }

        return array_values(array_filter($names, fn ($name) => is_string($name) && trim($name) !== ''));
    }

    /**
     * The role ids a client may carry: the configured ones plus the roles the
     * configured names resolve to.
     *
     * @return array<int, int>
     */
    public static function roleIds(): array
    {
        $ids = array_merge(self::configuredIds(), self::idsOfNames());

        $ids = array_map('intval', $ids);

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * Exclude the clients from a `users` query.
     *
     * Written as one where-group so the caller's own conditions stay untouched,
     * and with `whereNull` on the role so an account created without a role is
     * still offered as an owner instead of silently disappearing.
     */
    public static function filter(mixed $query): mixed
    {
        $ids = self::roleIds();

        if ($ids === []) {
            return $query;
        }

        return $query->where(function ($builder) use ($ids) {
            $builder->whereNull('users.role_id')
                ->orWhereNotIn('users.role_id', $ids);
        });
    }

    /**
     * Whether the account belongs to a trading client.
     */
    public static function isClient(?User $user): bool
    {
        if (! $user || ! $user->role_id) {
            return false;
        }

        return in_array((int) $user->role_id, self::roleIds(), true);
    }

    /**
     * The ids named by the configuration, for a deployment that pins a role.
     *
     * @return array<int, int>
     */
    protected static function configuredIds(): array
    {
        $ids = config('crypto_call_center.trading.client_roles.ids', []);

        return is_array($ids) ? $ids : [];
    }

    /**
     * The ids of the configured role names.
     *
     * @return array<int, int>
     */
    protected static function idsOfNames(): array
    {
        $names = self::roleNames();

        if ($names === [] || ! Schema::hasTable('roles')) {
            return [];
        }

        return DB::table('roles')
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
