<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\CryptoCallCenter\Contracts\WithdrawalRequest as WithdrawalRequestContract;
use Webkul\CryptoCallCenter\Events\TradingEventReceived;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Models\WithdrawalCard;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;
use Webkul\CryptoCallCenter\Repositories\WithdrawalRequestRepository;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The number the payout desk is supposed to read out, never in the queue.
 */
const WITHDRAWAL_TEST_PAN = '4242424242424242';

/**
 * The secret both applications are configured with for the reveal.
 */
const WITHDRAWAL_TEST_SECRET = 'withdrawal-test-secret';

/**
 * The trading application owns the payout tables, so an installation that has
 * not deployed them has nothing to assert against.
 */
function withdrawalTablesDeployed(): bool
{
    return WithdrawalRequestRepository::available();
}

/**
 * Skip the payout checks on an installation without the trading tables.
 */
function withdrawalRequireTables(): void
{
    if (! withdrawalTablesDeployed()) {
        test()->markTestSkipped('The trading `withdrawal_requests` table is not deployed on this installation.');
    }
}

/**
 * A client of the trading cabinet.
 */
function withdrawalClient(): User
{
    return User::create([
        'name' => 'payout-client-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => getDefaultAdmin()->role_id,
    ]);
}

/**
 * An account limited to a custom set of role permissions.
 *
 * @param  array<int, string>  $permissions
 */
function withdrawalOperator(array $permissions): User
{
    $role = Role::create([
        'name' => 'payout-role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'payout-operator-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * A card number sealed the way the trading application seals it: AES-256-GCM
 * under the secret both applications share, nonce and tag stored beside the
 * ciphertext.
 */
function withdrawalSeal(string $digits, string $secret = WITHDRAWAL_TEST_SECRET): string
{
    $key = hash('sha256', $secret, true);

    $nonce = random_bytes(12);

    $tag = '';

    $ciphertext = openssl_encrypt($digits, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

    return 'v1:'.base64_encode($nonce).':'.base64_encode($tag).':'.base64_encode((string) $ciphertext);
}

/**
 * A payout request of a fresh client, with the card it should be paid to.
 *
 * @param  array<string, mixed>  $overrides
 */
function withdrawalFixture(?string $panEncrypted = null, array $overrides = []): WithdrawalRequest
{
    $client = withdrawalClient();

    $card = WithdrawalCard::create([
        'user_id' => $client->id,
        'holder_name' => 'IVAN IVANOV',
        'brand' => 'visa',
        'last4' => substr(WITHDRAWAL_TEST_PAN, -4),
        'expiry' => '12/30',
        'pan_encrypted' => $panEncrypted ?? '',
        'fingerprint' => bin2hex(random_bytes(16)),
    ]);

    return WithdrawalRequest::create(array_merge([
        'user_id' => $client->id,
        'withdrawal_card_id' => $card->id,
        'amount_usd' => 250,
        'method' => WithdrawalRequest::METHOD_CARD,
        'wallet_address' => null,
        'status' => WithdrawalRequest::STATUS_PENDING,
        'is_approved' => false,
    ], $overrides));
}

/**
 * The row of the queue the test just wrote, as the grid serves it.
 *
 * @return array<string, mixed>|null
 */
function withdrawalGridRecord($response, int $id): ?array
{
    return collect($response->json('records'))->firstWhere('id', $id);
}

/**
 * A payout event as the trading application writes it into `trading_events`.
 *
 * @param  array<string, mixed>  $payload
 */
function withdrawalEventModel(array $payload, int $userId): TradingEvent
{
    return (new TradingEvent)->newFromBuilder([
        'id' => random_int(900000000, 999999999),
        'user_id' => $userId,
        'event_type' => TradingEvent::TYPE_WITHDRAWAL_REQUESTED,
        'payload' => json_encode($payload),
        'created_at' => now()->toDateTimeString(),
    ]);
}

it('lists the payout queue with the masked card and never the number', function () {
    withdrawalRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => WITHDRAWAL_TEST_SECRET]);

    $request = withdrawalFixture(withdrawalSeal(WITHDRAWAL_TEST_PAN));

    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.withdrawals.index'))
        ->assertOk();

    $record = withdrawalGridRecord($response, $request->id);

    expect($record)->not->toBeNull()
        /** The queue shows the last four digits, so the desk knows where the money goes. */
        ->and($record['requisites'])->toContain('4242')
        /** The number itself is opened on the request page and nowhere else. */
        ->and($record['requisites'])->not->toContain(WITHDRAWAL_TEST_PAN)
        ->and($record['amount'])->toContain('250')
        ->and($record['status'])->toContain(trans('crypto_call_center::app.withdrawals.status.pending'));
});

it('shows the wallet behind a crypto payout, because a wallet is public', function () {
    withdrawalRequireTables();

    $wallet = 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE';

    $request = withdrawalFixture(null, [
        'method' => WithdrawalRequest::METHOD_CRYPTO,
        'withdrawal_card_id' => null,
        'wallet_address' => $wallet,
    ]);

    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.withdrawals.index'))
        ->assertOk();

    $record = withdrawalGridRecord($response, $request->id);

    expect($record)->not->toBeNull()
        ->and($record['requisites'])->toContain($wallet);
});

it('guards the queue with the withdrawals permission', function () {
    $allowed = withdrawalOperator(['withdrawals']);
    $denied = withdrawalOperator(['contacts']);

    $this->actingAs($allowed, 'user')
        ->get(route('admin.crypto_call_center.withdrawals.index'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.withdrawals.index.title'));

    $this->actingAs($denied, 'user')
        ->get(route('admin.crypto_call_center.withdrawals.index'))
        ->assertUnauthorized();
});

it('opens a payout request only for the account that may reveal the number', function () {
    withdrawalRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => WITHDRAWAL_TEST_SECRET]);

    $request = withdrawalFixture(withdrawalSeal(WITHDRAWAL_TEST_PAN));

    /** The queue is not the reveal: seeing the list does not open the number. */
    $this->actingAs(withdrawalOperator(['withdrawals']), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertUnauthorized();

    $this->actingAs(withdrawalOperator(['withdrawals', 'withdrawals.card']), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSee('4242 4242 4242 4242');
});

it('marks the reveal action for the account that holds the reveal permission', function () {
    withdrawalRequireTables();

    $request = withdrawalFixture(null);

    $withoutReveal = $this->actingAs(withdrawalOperator(['withdrawals']), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.withdrawals.index'))
        ->assertOk();

    $withReveal = $this->actingAs(withdrawalOperator(['withdrawals', 'withdrawals.card']), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.withdrawals.index'))
        ->assertOk();

    $cardUrl = route('admin.crypto_call_center.withdrawals.card', $request->id);

    /** The action is the reveal, so it exists only for the account that may use it. */
    expect(data_get(withdrawalGridRecord($withoutReveal, $request->id), 'actions.*.url'))
        ->not->toContain($cardUrl)
        ->and(data_get(withdrawalGridRecord($withReveal, $request->id), 'actions.*.url'))
        ->toContain($cardUrl);
});

it('says the number cannot be opened when the shared key is missing', function () {
    withdrawalRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => '']);

    $request = withdrawalFixture(withdrawalSeal(WITHDRAWAL_TEST_PAN));

    $this->actingAs(withdrawalOperator(['withdrawals', 'withdrawals.card']), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.withdrawals.card.not-configured'), false)
        ->assertDontSee('4242 4242 4242 4242');
});

it('says the row is unreadable when the key does not match it', function () {
    withdrawalRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => 'a-different-secret']);

    $request = withdrawalFixture(withdrawalSeal(WITHDRAWAL_TEST_PAN));

    $this->actingAs(withdrawalOperator(['withdrawals', 'withdrawals.card']), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.withdrawals.card.unreadable'), false)
        ->assertDontSee('4242 4242 4242 4242');
});

it('answers 404 for a request that does not exist', function () {
    withdrawalRequireTables();

    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', 2147483000))
        ->assertNotFound();
});

it('resolves a payout through its contract', function () {
    withdrawalRequireTables();

    $request = withdrawalFixture(null);

    /** Concord binds the contract to the model, so no consumer names the class. */
    expect(app(WithdrawalRequestContract::class))->toBeInstanceOf(WithdrawalRequest::class)
        ->and(app(WithdrawalRequestRepository::class)->findWithCard($request->id)?->id)->toBe($request->id);
});

it('words a payout request for the timeline without the number', function () {
    $event = withdrawalEventModel([
        'request_id' => 7,
        'amount' => 250,
        'method' => 'card',
        'status' => 'pending',
        'card_last4' => '4242',
        'card_brand' => 'Visa',
        'wallet_address' => null,
        'balance' => 1740,
    ], getDefaultAdmin()->id);

    expect(TradingEventPresenter::supports(TradingEvent::TYPE_WITHDRAWAL_REQUESTED))->toBeTrue()
        ->and(TradingEventPresenter::title($event, 'en'))
        ->toBe(trans('crypto_call_center::app.trading-events.activity.withdrawal', ['amount' => '250'], 'en'))
        ->and(TradingEventPresenter::badge($event, 'en'))
        ->toBe(trans('crypto_call_center::app.trading-events.badge.withdrawal', [], 'en'))
        /** A payout is money leaving the account, so it is not the green of a deposit. */
        ->and(TradingEventPresenter::badgeClass($event))->toBe('label-inactive')
        ->and(TradingEventPresenter::comment($event, 'en'))
        ->toContain('**** 4242')
        ->toContain('Visa')
        ->toContain('Balance: $1 740')
        ->not->toContain(WITHDRAWAL_TEST_PAN);
});

it('masks the wallet in the timeline of a crypto payout', function () {
    $wallet = 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE';

    $event = withdrawalEventModel([
        'amount' => 100,
        'method' => 'crypto',
        'card_last4' => null,
        'card_brand' => null,
        'wallet_address' => $wallet,
    ], getDefaultAdmin()->id);

    $comment = TradingEventPresenter::comment($event, 'en');

    /** The middle of the address is dropped, so a screenshot does not publish it. */
    expect($comment)->toContain('Wallet: TQn9Y2...bLSE')
        ->and($comment)->not->toContain($wallet);
});

it('writes a payout request into the timeline of the owning lead, masked', function () {
    useAdminLocale('en');

    $user = getDefaultAdmin();

    $pipeline = securityTestPipeline();

    $lead = Lead::create([
        'title' => 'Payout Lead '.bin2hex(random_bytes(4)),
        'user_id' => $user->id,
        'lead_value' => 500,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);

    $event = withdrawalEventModel([
        'amount' => 250,
        'method' => 'card',
        'card_last4' => '4242',
        'card_brand' => 'Visa',
        'wallet_address' => null,
    ], $user->id);

    event(new TradingEventReceived($event));

    $activityId = DB::table('trading_event_activities')
        ->where('trading_event_id', $event->id)
        ->value('activity_id');

    $activity = $activityId ? Activity::find($activityId) : null;

    expect($activity)->not->toBeNull()
        ->and($activity->title)->toBe(trans(
            'crypto_call_center::app.trading-events.activity.withdrawal',
            ['amount' => '250'],
            'en'
        ))
        ->and($activity->comment)->toContain('**** 4242')
        ->and($activity->comment)->not->toContain(WITHDRAWAL_TEST_PAN)
        ->and($lead->activities()->whereKey($activity->id)->count())->toBe(1);
});
