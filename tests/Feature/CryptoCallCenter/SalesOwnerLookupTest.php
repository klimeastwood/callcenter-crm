<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\CryptoCallCenter\DataGrids\Overrides\LeadDataGrid;
use Webkul\CryptoCallCenter\Repositories\SalesOwnerUserRepository;
use Webkul\CryptoCallCenter\Support\TradingClients;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The Sales Owner lookup must not offer a trading client as a manager.
 *
 * The users table is shared with the trading cabinet, where every self-registered
 * trader is a CRM user. The lookup is narrowed through a repository whose model
 * carries a global scope, so the class is tested on the three surfaces that use
 * it: the repository itself, the attribute lookup the lead form calls and the
 * filter of the lead list.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['crypto_call_center.trading.client_roles.names' => ['Trading Client']]);
});

/**
 * An account with the given role, created for one test.
 */
function salesOwnerTestUser(string $roleName, string $prefix): User
{
    $role = Role::create([
        'name' => $roleName,
        'description' => 'Created by SalesOwnerLookupTest.',
        'permission_type' => 'custom',
        'permissions' => [],
    ]);

    return User::create([
        'name' => $prefix.'-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

it('recognises a trading client by the role it carries', function () {
    $client = salesOwnerTestUser('Trading Client', 'Client');
    $staff = salesOwnerTestUser('Call Center Operator', 'Staff');

    expect(TradingClients::isClient($client))->toBeTrue()
        ->and(TradingClients::isClient($staff))->toBeFalse()
        ->and(TradingClients::isClient(null))->toBeFalse();
});

it('drops the clients from the staff repository and keeps the rest', function () {
    $client = salesOwnerTestUser('Trading Client', 'Client');
    $staff = salesOwnerTestUser('Call Center Operator', 'Staff');

    $names = app(SalesOwnerUserRepository::class)
        ->where('status', 1)
        ->get()
        ->pluck('name')
        ->all();

    expect($names)->toContain($staff->name)
        ->and($names)->not->toContain($client->name);

    /** The account itself stays visible everywhere else. */
    expect(User::query()->whereKey($client->id)->exists())->toBeTrue();
});

it('offers only the staff in the Sales Owner attribute lookup', function () {
    $client = salesOwnerTestUser('Trading Client', 'Client');
    $staff = salesOwnerTestUser('Call Center Operator', 'Staff');

    $this->actingAs(getDefaultAdmin(), 'user');

    $options = collect(app(AttributeRepository::class)->getLookUpOptions('users'))->pluck('name')->all();

    expect($options)->toContain($staff->name)
        ->and($options)->not->toContain($client->name);
});

it('points the Sales Person filter of the lead grid at the staff repository', function () {
    /** The grid reads the default pipeline while it is constructed. */
    securityTestPipeline();

    $grid = app(LeadDataGrid::class);

    $grid->prepareColumns();

    $column = collect($grid->getColumns())->first(fn ($column) => $column->getIndex() === 'sales_person');

    expect($column)->not->toBeNull()
        ->and($column->getFilterableOptions()['repository'] ?? null)
        ->toBe(SalesOwnerUserRepository::class);
});

it('answers the grid lookup with the staff only', function () {
    $client = salesOwnerTestUser('Trading Client', 'Client');
    $staff = salesOwnerTestUser('Call Center Operator', 'Staff');

    securityTestPipeline();

    $grid = app(LeadDataGrid::class);

    $grid->prepareColumns();

    $column = collect($grid->getColumns())->first(fn ($column) => $column->getIndex() === 'sales_person');

    $repository = $column->getFilterableOptions()['repository'];

    $options = collect(app($repository)
        ->select(['name as label', 'name as value'])
        ->where('name', 'LIKE', '%'.substr($staff->name, 0, 5).'%')
        ->get())
        ->pluck('value')
        ->all();

    expect($options)->toContain($staff->name)
        ->and($options)->not->toContain($client->name);
});

it('answers the look-up endpoint of the sales person filter with the staff only', function () {
    $client = salesOwnerTestUser('Trading Client', 'Client');
    $staff = salesOwnerTestUser('Call Center Operator', 'Staff');

    securityTestPipeline();

    /** The toolbar asks this endpoint for the options of the dropdown. */
    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->getJson(route('admin.datagrid.look_up', [
            'datagrid_id' => Crypt::encryptString(LeadDataGrid::class),
            'column' => 'sales_person',
            'search' => substr($staff->name, 0, 5),
        ]))
        ->assertOk();

    $values = collect($response->json())->pluck('value')->all();

    expect($values)->toContain($staff->name)
        ->and($values)->not->toContain($client->name);
});
