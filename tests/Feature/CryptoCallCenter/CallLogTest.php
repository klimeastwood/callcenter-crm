<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Core\Menu;
use Webkul\CryptoCallCenter\Contracts\CallLog as CallLogContract;
use Webkul\CryptoCallCenter\Models\CallLog;
use Webkul\CryptoCallCenter\Repositories\CallLogRepository;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * A lead the calls can belong to.
 *
 * The pipeline is resolved instead of assuming id 1, because the parallel
 * test databases are migrated but not seeded.
 */
function makeLeadForCallLog(): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Call Log Lead '.bin2hex(random_bytes(4)),
        'user_id' => getDefaultAdmin()->id,
        'lead_value' => 1000,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * A user limited to a custom set of role permissions and a data scope.
 */
function makeCallLogUser(array $permissions, string $viewPermission = 'global'): User
{
    $role = Role::create([
        'name' => 'role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'user-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => $viewPermission,
        'role_id' => $role->id,
    ]);
}

/**
 * Store one call for the given lead, so each test only states what it checks.
 */
function makeCallLog(Lead $lead, array $attributes = []): CallLog
{
    return app(CallLogRepository::class)->create(array_merge([
        'lead_id' => $lead->id,
        'user_id' => getDefaultAdmin()->id,
        'phone_number' => '+79001234567',
        'duration' => 95,
        'status' => CallLog::STATUS_COMPLETED,
        'call_date' => now(),
    ], $attributes));
}

it('stores a call for a lead through the repository', function () {
    $lead = makeLeadForCallLog();

    $call = makeCallLog($lead, [
        'recording_url' => 'https://cdn.example.com/calls/1.mp3',
        'transcript' => 'Client asked about a BTC deposit.',
    ]);

    expect($call)->toBeInstanceOf(CallLog::class)
        ->and($call->lead->id)->toBe($lead->id)
        ->and($call->duration)->toBe(95)
        ->and($call->call_date)->toBeInstanceOf(DateTimeInterface::class)
        ->and(app(CallLogRepository::class)->findByLead($lead->id))->toHaveCount(1);
});

it('resolves the model through its contract', function () {
    $lead = makeLeadForCallLog();

    $call = app(CallLogContract::class)->create([
        'lead_id' => $lead->id,
        'phone_number' => '+79007654321',
        'duration' => 10,
        'status' => CallLog::STATUS_MISSED,
        'call_date' => now(),
    ]);

    /**
     * The count is scoped to the lead instead of the whole table: a development
     * database may already hold demo calls, and this test must stay green there.
     */
    expect($call)->toBeInstanceOf(CallLog::class)
        ->and(
            CallLog::query()
                ->where('lead_id', $lead->id)
                ->where('status', CallLog::STATUS_MISSED)
                ->count()
        )->toBe(1);
});

it('renders a talk time as minutes and seconds', function () {
    expect(CallLog::formatDuration(0))->toBe('00:00')
        ->and(CallLog::formatDuration(65))->toBe('01:05')
        ->and(CallLog::formatDuration(3670))->toBe('1:01:10')
        ->and(CallLog::formatDuration(null))->toBe('00:00');
});

it('lists the calls on the admin screen', function () {
    $lead = makeLeadForCallLog();

    makeCallLog($lead, [
        'recording_url' => 'https://cdn.example.com/calls/1.mp3',
        'transcript' => 'Client asked about a BTC deposit.',
    ]);

    /**
     * The title comes from the package translation file, so it follows the panel locale instead of
     * being asserted as a fixed English string.
     */
    test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.call_logs.index'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.call-logs.index.title'));

    /** The menu item is registered through the package config, not by editing the Admin package. */
    $menuKeys = app(Menu::class)->getItems('admin')->map(fn ($item) => $item->getKey());

    expect($menuKeys)->toContain('call_logs');

    $response = test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.call_logs.index'))
        ->assertOk();

    /**
     * The call written above carries the current time, so it is the first record
     * of the newest-first list even when a development database already holds
     * demo calls. The total is therefore only checked to be non-empty.
     */
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1);

    $record = $response->json('records.0');

    expect($record['phone_number'])->toBe('+79001234567')
        ->and($record['duration'])->toBe('01:35')
        ->and($record['status'])->toContain(trans('crypto_call_center::app.call-logs.status.completed'))
        ->and($record['recording_url'])->toContain('https://cdn.example.com/calls/1.mp3')
        ->and($record['transcript'])->toBe('Client asked about a BTC deposit.');
});

it('guards the screen with the package ACL entry', function () {
    $allowed = makeCallLogUser(['call_logs']);
    $denied = makeCallLogUser(['crypto_call_center']);

    test()
        ->actingAs($allowed, 'user')
        ->get(route('admin.crypto_call_center.call_logs.index'))
        ->assertOk();

    test()
        ->actingAs($denied, 'user')
        ->get(route('admin.crypto_call_center.call_logs.index'))
        ->assertUnauthorized();
});

it('shows a manager only their own calls when the role scope is individual', function () {
    $lead = makeLeadForCallLog();
    $manager = makeCallLogUser(['call_logs'], 'individual');

    makeCallLog($lead, ['phone_number' => '+79000000001']);
    makeCallLog($lead, ['phone_number' => '+79000000002', 'user_id' => $manager->id]);

    $response = test()
        ->actingAs($manager, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.call_logs.index'))
        ->assertOk();

    expect($response->json('records'))->toHaveCount(1)
        ->and($response->json('records.0.phone_number'))->toBe('+79000000002');
});
