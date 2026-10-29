<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Core\Menu;
use Webkul\CryptoCallCenter\DataGrids\Overrides\ActivityDataGrid as OperatorActivityDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Overrides\LeadDataGrid as OperatorLeadDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Overrides\OrganizationDataGrid as OperatorOrganizationDataGrid;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The permissions the package declares for the operator role.
 */
function operatorRolePermissions(): array
{
    return config('crypto_call_center.roles.operator.permissions');
}

/**
 * The name the provisioning command looks the role up by.
 */
function operatorRoleName(): string
{
    return config('crypto_call_center.roles.operator.name');
}

/**
 * Run the provisioning command and return the role it created or refreshed.
 *
 * `--sync` is passed because a development database may already carry the role
 * from an earlier release, with a narrower permission list than the package now
 * declares: the assertions describe the packaged definition, not that stale row.
 */
function provisionedOperatorRole(): Role
{
    Artisan::call('crypto-call-center:operator-role', ['--sync' => true]);

    return Role::query()->where('name', operatorRoleName())->firstOrFail();
}

/**
 * A user holding the provisioned operator role.
 */
function makeOperatorUser(): User
{
    return User::create([
        'name' => 'Operator '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => provisionedOperatorRole()->id,
    ]);
}

/**
 * The URLs of the mass actions a grid offers to the acting user.
 */
function massActionUrlsOf(string $gridClass): array
{
    $grid = datagrid($gridClass);

    $grid->prepareMassActions();

    return collect($grid->getMassActions())->pluck('url')->values()->all();
}

it('provisions the operator role declared by the package', function () {
    /**
     * The role is created under its own name: a development database may already
     * carry the packaged role from an earlier release, and the command leaves an
     * existing role untouched, so the assertion about the packaged definition
     * would otherwise depend on what that row happens to hold.
     */
    $name = operatorRoleName().' '.uniqid();

    $this->artisan('crypto-call-center:operator-role', ['--name' => $name])->assertSuccessful();

    $role = Role::query()->where('name', $name)->sole();

    expect($role->permission_type)->toBe('custom')
        ->and($role->permissions)->toBe(operatorRolePermissions())
        ->and($role->description)->toBe(config('crypto_call_center.roles.operator.description'));
});

it('leaves an existing role alone unless syncing is requested', function () {
    $this->artisan('crypto-call-center:operator-role')->assertSuccessful();

    Role::query()->where('name', operatorRoleName())->sole()->update(['permissions' => ['leads']]);

    $this->artisan('crypto-call-center:operator-role')->assertSuccessful();

    expect(Role::query()->where('name', operatorRoleName())->count())->toBe(1)
        ->and(Role::query()->where('name', operatorRoleName())->sole()->permissions)->toBe(['leads']);

    $this->artisan('crypto-call-center:operator-role', ['--sync' => true])->assertSuccessful();

    expect(Role::query()->where('name', operatorRoleName())->sole()->permissions)->toBe(operatorRolePermissions());
});

it('refuses an ACL key that the application no longer declares', function () {
    config(['crypto_call_center.roles.operator.permissions' => ['leads', 'leads.teleport']]);

    /**
     * A fresh name keeps the assertion meaningful on a developer database that
     * already carries the provisioned role: the command must not create a new
     * role from a broken definition.
     */
    $name = 'Operator Role '.uniqid();

    $this->artisan('crypto-call-center:operator-role', ['--name' => $name])
        ->expectsOutputToContain('leads.teleport')
        ->assertFailed();

    expect(Role::query()->where('name', $name)->exists())->toBeFalse();
});

it('shows the operator only the sections of the role', function () {
    $operator = makeOperatorUser();

    $this->actingAs($operator, 'user');

    $keys = app(Menu::class)->getItems('admin')->map(fn ($item) => $item->getKey())->all();

    /**
     * The operator works the pipeline and answers the phone, so the call history
     * and the payout queue it reads out to a client sit on its menu as well. The
     * dashboard, the catalogue and the settings stay out: none of them is part of
     * the desk the role covers.
     */
    expect($keys)->toContain('leads', 'contacts', 'activities', 'crypto_call_center', 'call_logs', 'withdrawals')
        ->and($keys)->not->toContain(
            'dashboard',
            'quotes',
            'products',
            'mail',
            'settings',
            'configuration',
            'help',
        );
});

it('answers the operator screens and refuses every other section', function () {
    securityTestPipeline();

    $operator = makeOperatorUser();

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
        $this->actingAs($operator, 'user')->get($url)->assertOk();
    }

    foreach ([
        route('admin.dashboard.index'),
        route('admin.quotes.index'),
        route('admin.products.index'),
        route('admin.settings.users.index'),
        route('admin.settings.roles.index'),
        route('admin.configuration.index'),
    ] as $url) {
        $this->actingAs($operator, 'user')->get($url)->assertUnauthorized();
    }

    /**
     * The payout queue is masked for the role: revealing the full card number is
     * the supervisor's action, so the card route refuses an operator even on a
     * request it may not have been offered.
     */
    $this->actingAs($operator, 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', 1))
        ->assertUnauthorized();

    /**
     * Reading the deposit queue is part of the desk, but a deposit is credited
     * only by the supervisor: the request page and the decision stay closed.
     */
    $this->actingAs($operator, 'user')
        ->get(route('admin.crypto_call_center.deposits.show', 1))
        ->assertUnauthorized();

    $this->actingAs($operator, 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', 1))
        ->assertUnauthorized();

    /**
     * The delete action is hidden on the card and the endpoint behind it refuses
     * the request as well: the grid only mirrors what the middleware enforces.
     */
    $this->actingAs($operator, 'user')
        ->delete(route('admin.leads.delete', 1))
        ->assertUnauthorized();

    /**
     * Nothing on the operator screens links to a screen the role cannot open.
     * The wordmark and the breadcrumb root used to point at the dashboard, which
     * answers 401 for this role.
     */
    $this->actingAs($operator, 'user')
        ->get(route('admin.leads.index'))
        ->assertOk()
        ->assertDontSee(route('admin.dashboard.index'), false);

    /**
     * A role that holds `dashboard` keeps the shipped trail untouched.
     */
    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.leads.index'))
        ->assertOk()
        ->assertSee(route('admin.dashboard.index'), false);
});

it('points the error page home button at a screen the role may open', function () {
    /**
     * The shipped error page is only rendered outside debug mode (the handler
     * falls back to Laravel's page in debug), so the view is rendered directly
     * instead of through a request.
     */
    $operator = makeOperatorUser();

    $this->actingAs($operator, 'user');

    expect(view('admin::errors.index', ['errorCode' => '401'])->render())
        ->toContain(route('admin.leads.index'))
        ->not->toContain(route('admin.dashboard.index'));

    $this->actingAs(getDefaultAdmin(), 'user');

    expect(view('admin::errors.index', ['errorCode' => '401'])->render())
        ->toContain(route('admin.dashboard.index'));
});

it('serves the package grids instead of the core ones', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    expect(datagrid(LeadDataGrid::class))->toBeInstanceOf(OperatorLeadDataGrid::class)
        ->and(datagrid(ActivityDataGrid::class))->toBeInstanceOf(OperatorActivityDataGrid::class)
        ->and(datagrid(OrganizationDataGrid::class))->toBeInstanceOf(OperatorOrganizationDataGrid::class);
});

it('prints a mass action only when the role may run it', function () {
    securityTestPipeline();

    $operator = makeOperatorUser();

    $this->actingAs($operator, 'user');

    expect(massActionUrlsOf(LeadDataGrid::class))
        ->toContain(route('admin.leads.mass_update'))
        ->not->toContain(route('admin.leads.mass_delete'));

    /**
     * The role may edit activities, so the grid offers the bulk update it is
     * allowed to run and withholds the delete it is not.
     */
    expect(massActionUrlsOf(ActivityDataGrid::class))
        ->toContain(route('admin.activities.mass_update'))
        ->not->toContain(route('admin.activities.mass_delete'));

    expect(massActionUrlsOf(OrganizationDataGrid::class))->toBe([]);

    $this->actingAs(getDefaultAdmin(), 'user');

    expect(massActionUrlsOf(LeadDataGrid::class))
        ->toContain(route('admin.leads.mass_update'), route('admin.leads.mass_delete'))
        ->and(massActionUrlsOf(ActivityDataGrid::class))
        ->toContain(route('admin.activities.mass_update'), route('admin.activities.mass_delete'))
        ->and(massActionUrlsOf(OrganizationDataGrid::class))
        ->toContain(route('admin.contacts.organizations.mass_delete'));
});

it('makes the operator the sales owner of the lead it creates', function () {
    $pipeline = securityTestPipeline();

    $operator = makeOperatorUser();

    $title = 'Operator Lead '.uniqid();

    /**
     * The form names somebody else on purpose: the role may only read the leads
     * it owns, so a lead left with the administrator would disappear from the
     * list of the very account that typed it in.
     */
    $this->actingAs($operator, 'user')
        ->post(route('admin.leads.store'), [
            'title' => $title,
            'lead_value' => 250,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
            'user_id' => getDefaultAdmin()->id,
        ]);

    $lead = Lead::query()->where('title', $title)->sole();

    expect($lead->user_id)->toBe($operator->id);

    /**
     * And the lead is readable at once: the scope filter reads `leads.user_id`,
     * so the row is what the operator sees on its grid, not a hidden record.
     */
    $this->actingAs($operator, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.leads.index', ['pipeline_id' => $pipeline->id]))
        ->assertOk()
        ->assertJsonPath('records.0.id', $lead->id);
});
