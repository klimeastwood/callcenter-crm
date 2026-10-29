<?php

namespace Webkul\CryptoCallCenter\Support;

use Webkul\User\Models\RoleProxy;
use Webkul\User\Models\User;

/**
 * The roles the package declares and the data scope it pins to them.
 *
 * Krayin keeps `view_permission` on the user and offers `global`, `group` and
 * `individual` in Settings -> Users, while the role itself carries no column for
 * it. A call center separates the private pipeline from the shared contact book:
 * an operator is pinned to `individual`, so the lead and activity lists only show
 * their own work (the contact book is shared separately by
 * `Webkul\CryptoCallCenter\Bouncer`), while a moderator is pinned to `global`
 * because it supervises the whole desk. The scope is declared next to the role in
 * `Config/roles.php` and enforced on the user model: whatever form, importer or
 * console command saves an account with a pinned role, the scope is rewritten to
 * the packaged value before the row is written.
 *
 * A role may build on another one (`inherits`), which is how the moderator is
 * kept a superset of the operator instead of a copy that drifts. Resolution
 * happens here so the config file, the provisioning command and the tests all
 * read the same answer.
 *
 * The class stays silent when the declaration is absent, so a package config
 * that does not pin a scope leaves the administrator's choice untouched.
 */
class OperatorScope
{
    /**
     * The scopes Krayin understands.
     */
    public const SCOPES = ['global', 'group', 'individual'];

    /**
     * The role the package speaks about when no other one is named.
     */
    public const DEFAULT_ROLE = 'operator';

    /**
     * The keys of the roles the package declares.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $declared = config('crypto_call_center.roles', []);

        if (! is_array($declared)) {
            return [];
        }

        return array_values(array_filter(array_keys($declared), function ($key) use ($declared) {
            return is_string($key) && is_array($declared[$key]);
        }));
    }

    /**
     * The declaration of a role exactly as the config file spells it.
     *
     * @return array<string, mixed>
     */
    public static function declared(string $role): array
    {
        $declared = config('crypto_call_center.roles.'.$role, []);

        return is_array($declared) ? $declared : [];
    }

    /**
     * The declaration of a role with the roles it inherits folded in.
     *
     * Permissions are unioned so a child can only ever add; every other key of
     * the child wins, which is how the moderator pins its own `global` scope
     * instead of inheriting the operator's `individual` one.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $role): array
    {
        return self::resolve($role, []);
    }

    /**
     * Every declared role, resolved against its parents, keyed by role key.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::keys() as $role) {
            $definitions[$role] = self::definition($role);
        }

        return $definitions;
    }

    /**
     * The name the role is stored under, or `null` when it has none.
     */
    public static function name(string $role = self::DEFAULT_ROLE): ?string
    {
        $name = self::definition($role)['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The name of the operator role - the package's historical entry point.
     */
    public static function roleName(): ?string
    {
        return self::name();
    }

    /**
     * The scope declared for the role, or `null` when the administrator is free.
     */
    public static function scope(string $role = self::DEFAULT_ROLE): ?string
    {
        $scope = self::definition($role)['view_permission'] ?? null;

        return is_string($scope) && in_array($scope, self::SCOPES, true) ? $scope : null;
    }

    /**
     * The ACL keys the role grants, parents included.
     *
     * @return array<int, string>
     */
    public static function permissions(string $role = self::DEFAULT_ROLE): array
    {
        return self::permissionList(self::definition($role));
    }

    /**
     * The id of the provisioned role, or `null` while it does not exist yet.
     */
    public static function roleId(string $role = self::DEFAULT_ROLE): ?int
    {
        $name = self::name($role);

        if ($name === null) {
            return null;
        }

        $roleModel = RoleProxy::modelClass();

        $id = $roleModel::query()->where('name', $name)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The key of the packaged role the account holds, or `null` for anything
     * else (an administrator, a custom role, an account without a role).
     */
    public static function roleOf(?User $user): ?string
    {
        if (! $user || ! $user->role_id) {
            return null;
        }

        $name = optional($user->role)->name;

        if (! is_string($name) || $name === '') {
            return null;
        }

        foreach (self::definitions() as $role => $definition) {
            if (($definition['name'] ?? null) === $name) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Rewrite the scope of the given account when it holds a pinned role.
     *
     * Returns whether the account was recognised as one of the packaged roles.
     * The method is meant to run from a `saving` listener, before the row is
     * persisted.
     */
    public static function enforce(User $user): bool
    {
        $role = self::roleOf($user);

        if ($role === null) {
            return false;
        }

        $scope = self::scope($role);

        if ($scope === null) {
            return false;
        }

        $user->view_permission = $scope;

        return true;
    }

    /**
     * Whether the account supervises the leads it may see.
     */
    public static function isSupervisor(?User $user): bool
    {
        $role = self::roleOf($user);

        if ($role === null) {
            return false;
        }

        return (bool) (self::definition($role)['supervisor'] ?? false);
    }

    /**
     * Whether a lead created by the account belongs to that account.
     */
    public static function assignsCreatorAsLeadOwner(?User $user): bool
    {
        $role = self::roleOf($user);

        if ($role === null) {
            return false;
        }

        return (bool) (self::definition($role)['assign_creator_as_lead_owner'] ?? false);
    }

    /**
     * Fold the `inherits` chain of a role into a single declaration.
     *
     * @param  array<int, string>  $chain  the roles already visited, to stop a cycle
     * @return array<string, mixed>
     */
    protected static function resolve(string $role, array $chain): array
    {
        $declared = self::declared($role);

        if ($declared === [] || in_array($role, $chain, true)) {
            return $declared;
        }

        $chain[] = $role;

        $parent = [];

        if (isset($declared['inherits']) && is_string($declared['inherits'])) {
            $parent = self::resolve($declared['inherits'], $chain);
        }

        return array_merge($parent, $declared, [
            'permissions' => array_values(array_unique(array_merge(
                self::permissionList($parent),
                self::permissionList($declared),
            ))),
        ]);
    }

    /**
     * The usable ACL keys of a declaration.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    protected static function permissionList(array $definition): array
    {
        $permissions = $definition['permissions'] ?? [];

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_filter($permissions, function ($permission) {
            return is_string($permission) && $permission !== '';
        }));
    }
}
