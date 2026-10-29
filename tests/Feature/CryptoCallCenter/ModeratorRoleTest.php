<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Core\Menu;
use Webkul\CryptoCallCenter\Support\BalanceAdjustmentAccess;
use Webkul\CryptoCallCenter\Support\OperatorScope;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The permissions the moderator is provisioned with, the operator ones included.
 */
function moderatorRolePermissions(): array
{
    return OperatorScope::permissions('moderator');
}

/**
 * The name the provisioning command stores the moderator role under.
 */
function moderatorRoleName(): string
{
    return (string) OperatorScope::name('moderator');
}

/**
 * Provision the moderator role and hand it back.
 *
 * `--sync` is passed because a development database may already carry the role
 * from an earlier release, with a narrower permission list than the package now
 * declares: the assertions describe the packaged definition, not that stale row.
 */
function provisionedModeratorRole(): Role
{
    Artisan::call('crypto-call-center:operator-role', ['role' => 'moderator', '--sync' => true]);

    return Role::query()->where('name', moderatorRoleName())->firstOrFail();
}

/**
 * Provision the operator role - the one the moderator inherits from.
 */
function provisionedOperatorRoleForModerator(): Role
{
    Artisan::call('crypto-call-center:operator-role', ['--sync' => true]);

    return Role::query()->where('name', (string) OperatorScope::name())->firstOrFail();
}

/**
 * An account on a packaged role, created with the scope an administrator picked.
 *
 * The scope is passed in because it proves the pinning: the package rewrites it
 * for either role, so the operator can only ever be `individual` and the
 * moderator only ever `global`, whatever the form submits.
 */
function callCenterAccount(string $role = 'moderator', string $scope = 'global'): User
{
    $roleModel = $role === 'moderator'
        ? provisionedModeratorRole()
        : provisionedOperatorRoleForModerator();

    return User::create([
        'name' => ucfirst($role).' '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => $scope,
        'role_id' => $roleModel->id,
    ]);
}

/**
 * A lead owned by the given account, on the pipeline the grid is read from.
 */
function moderatorTestLead(User $owner): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Moderator Lead '.bin2hex(random_bytes(4)),
        'user_id' => $owner->id,
        'lead_value' => 1000,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * The ids the lead grid lists for the account, read the way the Vue grid reads
 * them: the same request, the same scope filter.
 *
 * @return array<int, int>
 */
function visibleLeadIds(User $user): array
{
    $response = test()->actingAs($user, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.leads.index', ['pipeline_id' => securityTestPipeline()->id]))
        ->assertOk();

    return collect($response->json('records'))->pluck('id')->all();
}

/**
 * A person in the shared phone book, owned by the given account.
 */
function moderatorTestPerson(User $owner): Person
{
    return Person::create([
        'name' => 'Moderator Person '.bin2hex(random_bytes(4)),
        'emails' => [uniqid().'@example.com'],
        'contact_numbers' => [(string) random_int(100000000, 999999999)],
        'user_id' => $owner->id,
    ]);
}

/**
 * An activity in the working log, owned by the given account.
 */
function moderatorTestActivity(User $owner): Activity
{
    return Activity::create([
        'title' => 'Moderator Activity '.bin2hex(random_bytes(4)),
        'type' => 'call',
        'user_id' => $owner->id,
        'schedule_from' => now(),
        'schedule_to' => now()->addHour(),
        'is_done' => 1,
    ]);
}

it('provisions the moderator as a supervisor built on the operator role', function () {
    /**
     * A fresh name keeps the assertion meaningful on a developer database that
     * already carries the provisioned role, which the command leaves untouched.
     */
    $name = moderatorRoleName().' '.uniqid();

    $this->artisan('crypto-call-center:operator-role', ['role' => 'moderator', '--name' => $name])
        ->assertSuccessful();

    $role = Role::query()->where('name', $name)->sole();

    $operator = OperatorScope::permissions('operator');
    $moderator = moderatorRolePermissions();

    expect($role->permission_type)->toBe('custom')
        ->and($role->permissions)->toBe($moderator)

        /**
         * The moderator is the operator plus a desk to supervise, never a copy:
         * every key the operator holds is in the moderator definition, so a
         * screen added to the operator reaches the moderator on the next run.
         */
        ->and(array_values(array_diff($operator, $moderator)))->toBe([])
        ->and($moderator)->toContain(
            'leads',
            'leads.create',
            'leads.view',
            'leads.edit',
            'contacts.persons.edit',
            'contacts.organizations.create',
            'contacts.organizations.edit',
            'activities.edit',
            'crypto_call_center.balance',
            'call_logs',
            'withdrawals',
            'withdrawals.card',
            'deposits',
            'deposits.review',
        )

        /**
         * The supervision is the desk, not the company: no settings, no
         * catalogue, no quotes and no deleting.
         */
        ->and($moderator)->not->toContain(
            'dashboard',
            'settings',
            'settings.users',
            'settings.roles',
            'settings.groups',
            'configuration',
            'products',
            'quotes',
            'mail',
            'leads.delete',
            'leads.mass_delete',
            'help',
        );
});

it('pins the moderator to the global data scope on every save', function () {
    expect(OperatorScope::scope('moderator'))->toBe('global')
        ->and(OperatorScope::scope('operator'))->toBe('individual');

    /**
     * The supervisor sees every operator's desk by definition, so the account
     * form cannot narrow it: whatever scope the administrator submits is rewritten
     * before the row is stored.
     */
    expect(callCenterAccount('moderator', 'individual')->fresh()->view_permission)->toBe('global')
        ->and(callCenterAccount('moderator', 'group')->fresh()->view_permission)->toBe('global')

        /**
         * The operator keeps being pinned the other way, so the difference is in
         * the declaration and not in the account that happened to be created.
         */
        ->and(callCenterAccount('operator', 'global')->fresh()->view_permission)->toBe('individual');
});

it('marks the moderator as a supervisor and the operator as a plain owner', function () {
    $moderator = callCenterAccount('moderator');
    $operator = callCenterAccount('operator');

    expect(OperatorScope::roleOf($moderator))->toBe('moderator')
        ->and(OperatorScope::roleOf($operator))->toBe('operator')

        /**
         * The supervision flag widens the balance block from "the leads I own" to
         * "the leads I may read", and only the moderator asks for it.
         */
        ->and(OperatorScope::isSupervisor($moderator))->toBeTrue()
        ->and(OperatorScope::isSupervisor($operator))->toBeFalse()

        /**
         * The owner flag is inherited: a lead either of them types in is its own.
         */
        ->and(OperatorScope::assignsCreatorAsLeadOwner($moderator))->toBeTrue()
        ->and(OperatorScope::assignsCreatorAsLeadOwner($operator))->toBeTrue()

        /**
         * An administrator keeps both flags off, so a lead it creates keeps the
         * owner the form named.
         */
        ->and(OperatorScope::roleOf(getDefaultAdmin()))->toBeNull()
        ->and(OperatorScope::isSupervisor(getDefaultAdmin()))->toBeFalse();
});

it('shows the moderator the desk sections and nothing from the settings', function () {
    $moderator = callCenterAccount('moderator');

    $this->actingAs($moderator, 'user');

    $keys = app(Menu::class)->getItems('admin')->map(fn ($item) => $item->getKey())->all();

    expect($keys)->toContain('leads', 'contacts', 'activities', 'crypto_call_center', 'call_logs', 'withdrawals', 'deposits')
        ->and($keys)->not->toContain('dashboard', 'quotes', 'products', 'mail', 'settings', 'configuration', 'help');
});

it('opens the full payout card for the moderator and no administrative screen', function () {
    $moderator = callCenterAccount('moderator');

    foreach ([
        route('admin.leads.index'),
        route('admin.leads.create'),
        route('admin.contacts.persons.index'),
        route('admin.contacts.organizations.index'),
        route('admin.activities.index'),
        route('admin.crypto_call_center.wallets.index'),
        route('admin.crypto_call_center.call_logs.index'),
        route('admin.crypto_call_center.withdrawals.index'),
        route('admin.crypto_call_center.deposits.index'),
    ] as $url) {
        $this->actingAs($moderator, 'user')->get($url)->assertOk();
    }

    /**
     * The request page is the reveal and the decision: both belong to the desk
     * that supervises, so the review is reachable (404 for an unknown id, not
     * 401) while the operator below is refused.
     */
    $this->actingAs($moderator, 'user')
        ->get(route('admin.crypto_call_center.deposits.show', 999999))
        ->assertNotFound();

    expect(bouncer()->hasPermission('deposits.review'))->toBeTrue();

    $this->actingAs(callCenterAccount('operator'), 'user');

    expect(bouncer()->hasPermission('deposits.review'))->toBeFalse()
        ->and(bouncer()->hasPermission('deposits'))->toBeTrue();

    /**
     * The reveal is what separates the moderator from the operator: the queue is
     * masked for both, the card number is only opened for the desk that pays out.
     */
    $this->actingAs($moderator, 'user');

    expect(bouncer()->hasPermission('withdrawals.card'))->toBeTrue();

    $this->actingAs(callCenterAccount('operator'), 'user');

    expect(bouncer()->hasPermission('withdrawals.card'))->toBeFalse()
        ->and(bouncer()->hasPermission('withdrawals'))->toBeTrue();

    $this->actingAs($moderator, 'user');

    foreach ([
        route('admin.dashboard.index'),
        route('admin.quotes.index'),
        route('admin.products.index'),
        route('admin.settings.index'),
        route('admin.settings.users.index'),
        route('admin.settings.roles.index'),
        route('admin.settings.groups.index'),
        route('admin.configuration.index'),
    ] as $url) {
        $this->actingAs($moderator, 'user')->get($url)->assertUnauthorized();
    }
});

it('shows a moderator every lead even when the account form asked for less', function () {
    $operator = callCenterAccount('operator');
    $colleague = callCenterAccount('operator');

    $ownLead = moderatorTestLead($operator);
    $foreignLead = moderatorTestLead($colleague);

    /**
     * The account is created on the individual scope on purpose: the package
     * rewrites it to global, so the supervisor sees the colleague's lead without
     * the administrator having to widen anything.
     */
    $supervisor = callCenterAccount('moderator', 'individual');

    expect($supervisor->fresh()->view_permission)->toBe('global')
        ->and(visibleLeadIds($supervisor))->toContain($ownLead->id, $foreignLead->id);
});

it('shows a moderator every contact card and every activity of the desk', function () {
    $operator = callCenterAccount('operator');
    $colleague = callCenterAccount('operator');

    $ownPerson = moderatorTestPerson($operator);
    $foreignPerson = moderatorTestPerson($colleague);

    $ownActivity = moderatorTestActivity($operator);
    $foreignActivity = moderatorTestActivity($colleague);

    $supervisor = callCenterAccount('moderator');

    $this->actingAs($supervisor, 'user');

    $personIds = collect(
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.contacts.persons.index', [
                'filters' => ['id' => [$ownPerson->id, $foreignPerson->id]],
            ]))
            ->assertOk()
            ->json('records')
    )->pluck('id');

    expect($personIds)->toContain($ownPerson->id, $foreignPerson->id);

    $activityIds = collect(
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.activities.get', [
                'filters' => ['id' => [$ownActivity->id, $foreignActivity->id]],
            ]))
            ->assertOk()
            ->json('records')
    )->pluck('id');

    expect($activityIds)->toContain($ownActivity->id, $foreignActivity->id);
});

it('lets a moderator move money on any lead of the desk', function () {
    $operator = callCenterAccount('operator');
    $colleague = callCenterAccount('operator');

    $lead = moderatorTestLead($operator);
    $foreignLead = moderatorTestLead($colleague);

    $supervisor = callCenterAccount('moderator');

    $this->actingAs($supervisor, 'user');

    expect(BalanceAdjustmentAccess::allows($supervisor, $lead))->toBeTrue()
        ->and(BalanceAdjustmentAccess::allows($supervisor, $foreignLead))->toBeTrue();

    /**
     * The moderator owns every lead it creates too, so the balance block is also
     * open on its own pipeline.
     */
    expect(BalanceAdjustmentAccess::allows($supervisor, moderatorTestLead($supervisor)))->toBeTrue();
});

it('keeps the moderator global and the operator on its own leads', function () {
    $owner = callCenterAccount('operator');
    $colleague = callCenterAccount('operator');

    $lead = moderatorTestLead($owner);
    $foreignLead = moderatorTestLead($colleague);

    $supervisor = callCenterAccount('moderator', 'individual');

    $this->actingAs($supervisor, 'user');

    expect(BalanceAdjustmentAccess::allows($supervisor, $lead))->toBeTrue()
        ->and(BalanceAdjustmentAccess::allows($supervisor, $foreignLead))->toBeTrue();

    $this->actingAs($owner, 'user');

    expect(BalanceAdjustmentAccess::allows($owner, $lead))->toBeTrue()
        ->and(BalanceAdjustmentAccess::allows($owner, $foreignLead))->toBeFalse();

    /**
     * An administrator answers to nobody and is not bound by a scope.
     */
    $this->actingAs(getDefaultAdmin(), 'user');

    expect(BalanceAdjustmentAccess::allows(getDefaultAdmin(), $lead))->toBeTrue();
});

it('provisions every declared role with --all', function () {
    expect(OperatorScope::keys())->toContain('operator', 'moderator');

    $this->artisan('crypto-call-center:operator-role', ['--all' => true, '--sync' => true])
        ->assertSuccessful();

    $operator = Role::query()->where('name', (string) OperatorScope::name())->sole();
    $moderator = Role::query()->where('name', moderatorRoleName())->sole();

    expect($operator->permissions)->toBe(OperatorScope::permissions('operator'))
        ->and($moderator->permissions)->toBe(OperatorScope::permissions('moderator'))
        ->and($moderator->permissions)->toContain('withdrawals.card');
});

it('refuses to combine --all with the name of a single role', function () {
    $this->artisan('crypto-call-center:operator-role', ['--all' => true, '--name' => 'Anything'])
        ->expectsOutputToContain('--all')
        ->assertFailed();
});

it('refuses a role the package does not declare', function () {
    $this->artisan('crypto-call-center:operator-role', ['role' => 'supervisor'])
        ->expectsOutputToContain('supervisor')
        ->assertFailed();
});
