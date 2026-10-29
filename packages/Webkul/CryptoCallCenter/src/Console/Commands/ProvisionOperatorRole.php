<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Illuminate\Console\Command;
use Webkul\CryptoCallCenter\Support\OperatorScope;
use Webkul\User\Models\UserProxy;
use Webkul\User\Repositories\RoleRepository;

class ProvisionOperatorRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto-call-center:operator-role
                            {role=operator : Role key declared in Config/roles.php}
                            {--all : Provision every role the package declares}
                            {--sync : Rewrite the permissions of an existing role from the package config}
                            {--sync-users : Reset the data scope of every user holding the role to the packaged one}
                            {--name= : Role name to provision, defaults to the packaged definition}
                            {--description= : Role description, defaults to the packaged definition}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision the call center roles declared by the CryptoCallCenter package';

    /**
     * Execute the console command.
     *
     * The command is idempotent on purpose: it is meant to be re-run after every
     * deploy, and an administrator may have renamed the role or added a
     * permission by hand in Settings → Roles. Only `--sync` writes to an existing
     * role, so a manual edit survives the deploy unless the packaged definition
     * itself changed.
     */
    public function handle(RoleRepository $roleRepository): int
    {
        $targets = $this->targets();

        if ($targets === null) {
            return self::FAILURE;
        }

        foreach ($targets as $index => $target) {
            if ($index > 0) {
                $this->newLine();
            }

            $status = $this->provision($roleRepository, $target['key'], $target['definition']);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        return self::SUCCESS;
    }

    /**
     * The roles this run has to provision, or `null` when the request is void.
     *
     * `--all` is what a fresh install runs: every role the package declares is
     * provisioned in the order the config lists them, so a role that inherits
     * from another one is created after its parent.
     *
     * @return array<int, array{key: string, definition: array<string, mixed>}>|null
     */
    protected function targets(): ?array
    {
        $definitions = OperatorScope::definitions();

        if ($this->option('all')) {
            if ($this->option('name') !== null || $this->option('description') !== null) {
                $this->error('--name and --description describe a single role and cannot be combined with --all.');

                return null;
            }

            if ($definitions === []) {
                $this->error('No roles are declared in `crypto_call_center.roles`.');

                return null;
            }

            $targets = [];

            foreach ($definitions as $key => $definition) {
                $targets[] = ['key' => $key, 'definition' => $definition];
            }

            return $targets;
        }

        $key = (string) $this->argument('role');

        if (! array_key_exists($key, $definitions)) {
            $declared = array_keys($definitions);

            $this->error('The package does not declare a "'.$key.'" role.'.($declared === []
                ? ' `crypto_call_center.roles` is empty.'
                : ' Declared roles: '.implode(', ', $declared).'.'));

            return null;
        }

        return [['key' => $key, 'definition' => $definitions[$key]]];
    }

    /**
     * Create or refresh a single role from its resolved declaration.
     *
     * @param  array<string, mixed>  $declaration
     */
    protected function provision(RoleRepository $roleRepository, string $key, array $declaration): int
    {
        $name = (string) ($this->option('name') ?: ($declaration['name'] ?? 'Call Center '.ucfirst($key)));

        $description = (string) ($this->option('description') ?: ($declaration['description'] ?? ''));

        /**
         * The declaration arrives already resolved against the roles it inherits
         * (`inherits` in `Config/roles.php`), so the moderator is provisioned
         * with everything the operator grants plus its own additions and the two
         * can never drift apart.
         */
        $permissions = array_values(array_unique((array) ($declaration['permissions'] ?? [])));

        if ($permissions === []) {
            $this->error('No permissions are declared in `crypto_call_center.roles.'.$key.'.permissions`.');

            return self::FAILURE;
        }

        /**
         * A key that no longer exists would not break the role itself - the
         * middleware simply would never match it - but it would silently widen
         * or narrow the packaged scope, so the command refuses to build it.
         */
        $unknownPermissions = array_values(array_diff($permissions, $this->aclKeys()));

        if ($unknownPermissions !== []) {
            $this->error('The role declares ACL keys the application does not know:');

            foreach ($unknownPermissions as $permission) {
                $this->line('  - '.$permission);
            }

            return self::FAILURE;
        }

        /**
         * The data scope is declared next to the role but stored on the user.
         * A value Krayin does not understand would be silently ignored by the
         * lead grid, so the command refuses to provision the role with it.
         */
        $scope = $declaration['view_permission'] ?? null;

        if ($scope !== null && ! in_array($scope, OperatorScope::SCOPES, true)) {
            $this->error('The role declares an unknown data scope `'.(is_scalar($scope) ? $scope : gettype($scope)).'`. Expected one of: '.implode(', ', OperatorScope::SCOPES).'.');

            return self::FAILURE;
        }

        $role = $roleRepository->findOneWhere(['name' => $name]);

        if ($role && ! $this->option('sync')) {
            $this->warn("The \"{$name}\" role ({$key}) already exists and was left untouched. Pass --sync to reset its permissions from the package config.");

            $this->reportDifferences($role->permission_type, (array) $role->permissions, $permissions);

            if ($scope !== null) {
                $this->line("Packaged data scope: \"{$scope}\".");
            }

            $this->syncScope($role, $scope);

            return self::SUCCESS;
        }

        $attributes = [
            'name' => $name,
            'description' => $description,
            'permission_type' => 'custom',
            'permissions' => $permissions,
        ];

        $role = $role
            ? $roleRepository->update($attributes, $role->id)
            : $roleRepository->create($attributes);

        $this->info(($role->wasRecentlyCreated ? 'Created' : 'Updated')." the \"{$name}\" role ({$key}) with ".count($permissions).' permissions:');

        foreach ($permissions as $permission) {
            $this->line('  - '.$permission);
        }

        $this->newLine();

        $this->comment('Assign the role to a user under Settings → Users.');

        if ($scope !== null) {
            $this->line("Users of the role are pinned to the \"{$scope}\" data scope.");
        }

        $this->syncScope($role, $scope);

        return self::SUCCESS;
    }

    /**
     * Align the data scope of the accounts already holding the role.
     *
     * Krayin keeps `view_permission` on the user, so provisioning the role does
     * not touch accounts created before the scope was declared. The command only
     * reports that drift by default - an administrator may have had a reason to
     * widen one account - and rewrites every account with `--sync-users`. New
     * saves are pinned by the model listener registered in the service provider.
     */
    protected function syncScope(object $role, ?string $scope): void
    {
        if ($scope === null) {
            return;
        }

        $userModel = UserProxy::modelClass();

        $drifted = $userModel::query()
            ->where('role_id', $role->id)
            ->where(function ($query) use ($scope): void {
                $query->whereNull('view_permission')->orWhere('view_permission', '!=', $scope);
            });

        if (! $this->option('sync-users')) {
            $count = (clone $drifted)->count();

            if ($count > 0) {
                $this->warn("{$count} user(s) holding the role are not on the \"{$scope}\" data scope. Pass --sync-users to align them.");
            }

            return;
        }

        $updated = $drifted->update(['view_permission' => $scope]);

        $this->line("Aligned the \"{$scope}\" data scope on {$updated} user(s) holding the role.");
    }

    /**
     * Show what `--sync` would change, so a manual edit is not lost by accident.
     */
    protected function reportDifferences(string $permissionType, array $current, array $declared): void
    {
        if ($permissionType === 'all') {
            $this->line('The role currently grants every permission; --sync would replace that with the '.count($declared).' packaged permissions.');

            return;
        }

        $missing = array_values(array_diff($declared, $current));

        $extra = array_values(array_diff($current, $declared));

        $this->line('Missing compared to the package: '.($missing === [] ? 'none' : implode(', ', $missing)));

        $this->line('Granted beyond the package: '.($extra === [] ? 'none' : implode(', ', $extra)));
    }

    /**
     * Every ACL key the application declares.
     *
     * The keys are read from the merged config rather than from
     * `acl()->getItems()`, because the items are nested and already translated,
     * while the command only needs the flat set of keys the middleware matches.
     */
    protected function aclKeys(): array
    {
        return collect(config('acl', []))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }
}
