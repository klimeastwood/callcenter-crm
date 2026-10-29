<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\CryptoCallCenter\Support\OperatorScope;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * Provision the operator role and hand it back.
 *
 * `--sync` is passed because a development database may already carry the role
 * from an earlier release, with a narrower permission list than the package now
 * declares: the scope assertions describe the packaged definition.
 */
function provisionedOperatorRoleForScope(): Role
{
    Artisan::call('crypto-call-center:operator-role', ['--sync' => true]);

    return Role::query()->where('name', config('crypto_call_center.roles.operator.name'))->firstOrFail();
}

/**
 * An operator account created with the data scope an administrator picked.
 *
 * The scope is deliberately passed as `global`: the package has to rewrite it,
 * because a call center operator must never read the leads of a colleague.
 */
function operatorWithScope(string $scope = 'global'): User
{
    return User::create([
        'name' => 'Operator '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => $scope,
        'role_id' => provisionedOperatorRoleForScope()->id,
    ]);
}

/**
 * A lead owned by the given user, on the pipeline the grid is read from.
 */
function leadOwnedBy(User $owner): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Scope Lead '.bin2hex(random_bytes(4)),
        'user_id' => $owner->id,
        'lead_value' => 1000,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * A person in the shared contact book, owned by the given account.
 */
function personOwnedBy(User $owner): Person
{
    return Person::create([
        'name' => 'Scope Person '.bin2hex(random_bytes(4)),
        'emails' => [uniqid().'@example.com'],
        'contact_numbers' => [(string) random_int(100000000, 999999999)],
        'user_id' => $owner->id,
    ]);
}

/**
 * An organization in the shared contact book, owned by the given account.
 */
function organizationOwnedBy(User $owner): Organization
{
    return Organization::create([
        'name' => 'Scope Organization '.bin2hex(random_bytes(4)),
        'user_id' => $owner->id,
    ]);
}

/**
 * An activity owned by the given account, of a type the activity grid lists.
 */
function activityOwnedBy(User $owner): Activity
{
    return Activity::create([
        'title' => 'Scope Activity '.bin2hex(random_bytes(4)),
        'type' => 'call',
        'user_id' => $owner->id,
        'schedule_from' => now(),
        'schedule_to' => now()->addHour(),
        'is_done' => 1,
    ]);
}

it('shares the contact book while filtering every operator timeline', function () {
    $operator = operatorWithScope();
    $colleague = operatorWithScope();

    $ownPerson = personOwnedBy($operator);
    $foreignPerson = personOwnedBy($colleague);
    $ownOrganization = organizationOwnedBy($operator);
    $foreignOrganization = organizationOwnedBy($colleague);

    $ownActivity = activityOwnedBy($operator);
    $foreignActivity = activityOwnedBy($colleague);

    $foreignPerson->activities()->attach($foreignActivity->id);

    $this->actingAs($operator, 'user');

    /**
     * Contacts are the company phone book: the operator opens a person or an
     * organization created by a colleague, on the grid and on the record page,
     * otherwise a shared lead could not be worked.
     */
    $personIds = collect(
        $this->getJson(
            route('admin.contacts.persons.index', [
                'filters' => ['id' => [$ownPerson->id, $foreignPerson->id]],
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->json('records')
    )->pluck('id');

    expect($personIds)->toContain($ownPerson->id, $foreignPerson->id);

    $organizationIds = collect(
        $this->getJson(
            route('admin.contacts.organizations.index', [
                'filters' => ['id' => [$ownOrganization->id, $foreignOrganization->id]],
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->json('records')
    )->pluck('id');

    expect($organizationIds)->toContain($ownOrganization->id, $foreignOrganization->id);

    $this->get(route('admin.contacts.persons.view', $foreignPerson->id))->assertOk();
    $this->get(route('admin.contacts.organizations.edit', $foreignOrganization->id))->assertOk();

    /**
     * Activities are the working log, not the phone book: the shared contact does
     * not open the colleague's calls. The grid keeps the owner scope, so the
     * operator sees only what it logged itself.
     */
    $activityIds = collect(
        $this->getJson(
            route('admin.activities.get', [
                'filters' => ['id' => [$ownActivity->id, $foreignActivity->id]],
            ]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertOk()
            ->json('records')
    )->pluck('id');

    expect($activityIds)->toContain($ownActivity->id)
        ->and($activityIds)->not->toContain($foreignActivity->id);

    /**
     * The contact card opens, its timeline does not: the call log and notes of a
     * colleague are not part of the shared phone book.
     */
    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.contacts.persons.activities.index', $foreignPerson->id))
        ->assertUnauthorized();

    $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.contacts.persons.activities.index', $ownPerson->id))
        ->assertOk();
});

it('declares the individual scope next to the operator role', function () {
    $permissions = config('crypto_call_center.roles.operator.permissions');

    expect(OperatorScope::scope())->toBe('individual')
        ->and($permissions)->not->toContain('leads.delete');

    expect($permissions)->not->toContain('leads.mass_delete');
});

it('pins an operator account to the individual data scope on every save', function () {
    $operator = operatorWithScope('global');

    expect($operator->fresh()->view_permission)->toBe('individual');

    /**
     * The Settings → Users form, an importer or tinker can all submit a wider
     * scope; the model listener is the single place that refuses it.
     */
    $operator->update(['view_permission' => 'global']);

    expect($operator->fresh()->view_permission)->toBe('individual')
        ->and($operator->fresh()->role_id)->toBe(provisionedOperatorRoleForScope()->id);
});

it('leaves the data scope of every other role alone', function () {
    provisionedOperatorRoleForScope();

    $role = Role::create([
        'name' => 'role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => ['leads', 'leads.view'],
    ]);

    $user = User::create([
        'name' => 'Manager '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);

    expect($user->fresh()->view_permission)->toBe('global');
});

it('retrofits the scope onto operators stored before the role was pinned', function () {
    $operator = operatorWithScope();

    /**
     * Simulate the accounts that existed before the scope was declared: the
     * listener only guards writes, so their current value has to be repaired by
     * the provisioning command.
     */
    DB::table('users')->where('id', $operator->id)->update(['view_permission' => 'global']);

    $this->artisan('crypto-call-center:operator-role')->assertSuccessful();

    expect($operator->fresh()->view_permission)->toBe('global');

    $this->artisan('crypto-call-center:operator-role', ['--sync-users' => true])
        ->assertSuccessful();

    expect($operator->fresh()->view_permission)->toBe('individual');
});

it('refuses a data scope the application does not understand', function () {
    config(['crypto_call_center.roles.operator.view_permission' => 'everything']);

    $name = 'Scope Role '.uniqid();

    $this->artisan('crypto-call-center:operator-role', ['--name' => $name])
        ->expectsOutputToContain('everything')
        ->assertFailed();

    expect(Role::query()->where('name', $name)->exists())->toBeFalse();
});

it('lists only the leads the operator owns', function () {
    $pipeline = securityTestPipeline();

    $operator = operatorWithScope();
    $colleague = operatorWithScope();

    $ownLead = leadOwnedBy($operator);
    $foreignLead = leadOwnedBy($colleague);

    $response = $this->actingAs($operator, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.leads.index', ['pipeline_id' => $pipeline->id]))
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('records.0.id'))->toBe($ownLead->id)
        ->and($response->json('records.0.id'))->not->toBe($foreignLead->id)
        ->and($response->json('records.0.title'))->toBe($ownLead->title);
});

it('does not print a delete action on the lead grid for an operator', function () {
    securityTestPipeline();

    $operator = operatorWithScope();

    $this->actingAs($operator, 'user');

    $operatorGrid = datagrid(LeadDataGrid::class);
    $operatorGrid->prepareActions();

    expect(collect($operatorGrid->getActions())->pluck('icon')->all())
        ->toContain('icon-eye')
        ->not->toContain('icon-delete');

    $this->actingAs(getDefaultAdmin(), 'user');

    $adminGrid = datagrid(LeadDataGrid::class);
    $adminGrid->prepareActions();

    expect(collect($adminGrid->getActions())->pluck('icon')->all())
        ->toContain('icon-delete');
});

it('refuses to delete a lead, own or foreign, for an operator', function () {
    $operator = operatorWithScope();
    $colleague = operatorWithScope();

    $ownLead = leadOwnedBy($operator);
    $foreignLead = leadOwnedBy($colleague);

    /**
     * The row action is hidden for the role, and the endpoint refuses the
     * request as well: hiding a button is a convenience, not the protection.
     */
    foreach ([$ownLead, $foreignLead] as $lead) {
        $this->actingAs($operator, 'user')
            ->delete(route('admin.leads.delete', $lead->id))
            ->assertUnauthorized();
    }

    expect(Lead::query()->whereKey([$ownLead->id, $foreignLead->id])->count())->toBe(2);
});
