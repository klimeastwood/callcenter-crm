<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\CryptoCallCenter\Contracts\DepositRequest as DepositRequestContract;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Models\DepositCard;
use Webkul\CryptoCallCenter\Models\DepositRequest;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Repositories\DepositRequestRepository;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;
use Webkul\CryptoCallCenter\Services\Deposit\DepositBalanceGateway;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The deposit desk: who may see a request, what the approval does to the
 * balance, and what a rejection leaves behind.
 *
 * The tables belong to the trading application, so a case that needs rows skips
 * itself on an installation that has not deployed them. The gateway is replaced
 * by a stub wherever the test is about the CRM side of the decision, so the
 * assertions do not depend on the trading bridge running next to the suite.
 */
uses(DatabaseTransactions::class);

/**
 * The card number a deposit is matched against.
 */
const DEPOSIT_TEST_PAN = '4242424242424242';

/**
 * The secret both applications are configured with for the card.
 */
const DEPOSIT_TEST_SECRET = 'deposit-test-secret';

/**
 * The trading application owns the deposit tables.
 */
function depositTablesDeployed(): bool
{
    return DepositRequestRepository::available();
}

/**
 * Skip the deposit checks on an installation without the trading tables.
 */
function depositRequireTables(): void
{
    if (! depositTablesDeployed()) {
        test()->markTestSkipped('The trading `deposit_requests` table is not deployed on this installation.');
    }
}

/**
 * A client of the trading cabinet.
 */
function depositTestClient(): User
{
    return User::create([
        'name' => 'deposit-client-'.uniqid(),
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
function depositTestEmployee(array $permissions): User
{
    $role = Role::create([
        'name' => 'deposit-role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'deposit-employee-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * A card number sealed the way the trading application seals it.
 */
function depositSeal(string $digits, string $secret = DEPOSIT_TEST_SECRET): string
{
    $key = hash('sha256', $secret, true);

    $nonce = random_bytes(12);

    $tag = '';

    $ciphertext = openssl_encrypt($digits, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

    return 'v1:'.base64_encode($nonce).':'.base64_encode($tag).':'.base64_encode((string) $ciphertext);
}

/**
 * A pending deposit of a fresh client, with the card the money came from.
 *
 * @param  array<string, mixed>  $overrides
 */
function depositFixture(array $overrides = [], ?string $panEncrypted = null): DepositRequest
{
    $client = depositTestClient();

    $card = DepositCard::create([
        'user_id' => $client->id,
        'holder_name' => 'IVAN IVANOV',
        'brand' => 'visa',
        'last4' => substr(DEPOSIT_TEST_PAN, -4),
        'expiry' => '12/30',
        'pan_encrypted' => $panEncrypted ?? '',
        'fingerprint' => bin2hex(random_bytes(16)),
    ]);

    return DepositRequest::create(array_merge([
        'user_id' => $client->id,
        'deposit_card_id' => $card->id,
        'amount_usd' => 250,
        'status' => DepositRequest::STATUS_PENDING,
        'is_approved' => false,
    ], $overrides));
}

/**
 * A trading side that answers from memory and keeps the requests it was given.
 */
function depositTestGateway(?BalanceChange $change = null, ?TradingPlatformException $refusal = null): DepositBalanceGateway
{
    return new class($change, $refusal) implements DepositBalanceGateway
    {
        /** @var array<int, array<string, mixed>> */
        public array $calls = [];

        public function __construct(
            protected ?BalanceChange $change,
            protected ?TradingPlatformException $refusal,
        ) {}

        public function credit(int $userId, float $amount, string $reference, ?string $operatorName = null): BalanceChange
        {
            $this->calls[] = [
                'userId' => $userId,
                'amount' => $amount,
                'reference' => $reference,
                'operatorName' => $operatorName,
            ];

            if ($this->refusal) {
                throw $this->refusal;
            }

            return $this->change ?? new BalanceChange(0, $amount);
        }
    };
}

/**
 * The row of the queue the test just wrote, as the grid serves it.
 *
 * @return array<string, mixed>|null
 */
function depositGridRecord($response, int $id): ?array
{
    return collect($response->json('records'))->firstWhere('id', $id);
}

it('resolves the model through its contract and masks the card in the queue', function () {
    depositRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => DEPOSIT_TEST_SECRET]);

    $request = depositFixture([], depositSeal(DEPOSIT_TEST_PAN));

    expect(app(DepositRequestContract::class))->toBeInstanceOf(DepositRequest::class);

    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.deposits.index'))
        ->assertOk();

    $record = depositGridRecord($response, $request->id);

    expect($record)->not->toBeNull()
        /** The queue shows the last four digits, so the desk knows which card to check. */
        ->and($record['requisites'])->toContain('4242')
        /** The number itself is opened on the review page and nowhere else. */
        ->and($record['requisites'])->not->toContain(DEPOSIT_TEST_PAN)
        ->and($record['amount'])->toContain('250')
        ->and($record['status'])->toContain(trans('crypto_call_center::app.deposits.status.pending'));
});

it('guards the queue with the deposits permission', function () {
    $allowed = depositTestEmployee(['deposits']);
    $denied = depositTestEmployee(['contacts']);

    $this->actingAs($allowed, 'user')
        ->get(route('admin.crypto_call_center.deposits.index'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.deposits.index.title'));

    $this->actingAs($denied, 'user')
        ->get(route('admin.crypto_call_center.deposits.index'))
        ->assertUnauthorized();
});

it('hides the review action and refuses a decision without the review permission', function () {
    depositRequireTables();

    $request = depositFixture();

    $reader = depositTestEmployee(['deposits']);

    $gateway = depositTestGateway(new BalanceChange(100, 350));

    app()->instance(DepositBalanceGateway::class, $gateway);

    $response = $this->actingAs($reader, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.deposits.index'))
        ->assertOk();

    /** The reveal is the review, so a reader gets no button for it. */
    expect(data_get(depositGridRecord($response, $request->id), 'actions.*.url'))
        ->not->toContain(route('admin.crypto_call_center.deposits.show', $request->id));

    $this->actingAs($reader, 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', $request->id))
        ->assertUnauthorized();

    expect($gateway->calls)->toBeEmpty()
        ->and(DepositRequest::query()->findOrFail($request->id)->status)->toBe(DepositRequest::STATUS_PENDING);
});

it('opens the request, and the card number, only for the account that may review it', function () {
    depositRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => DEPOSIT_TEST_SECRET]);

    $request = depositFixture([], depositSeal(DEPOSIT_TEST_PAN));

    /** The queue is not the review: seeing the list does not open the number. */
    $this->actingAs(depositTestEmployee(['deposits']), 'user')
        ->get(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertUnauthorized();

    $this->actingAs(depositTestEmployee(['deposits', 'deposits.review']), 'user')
        ->get(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertOk()
        ->assertSee('4242 4242 4242 4242')
        ->assertSee(trans('crypto_call_center::app.deposits.review.approve'));
});

it('credits the balance and closes the request on approval', function () {
    depositRequireTables();

    config([
        'crypto_call_center.trading.driver' => 'http',
        'crypto_call_center.trading.client_roles.names' => ['Trading Client'],
    ]);

    $request = depositFixture();

    $gateway = depositTestGateway(new BalanceChange(100, 350));

    app()->instance(DepositBalanceGateway::class, $gateway);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', $request->id), ['note' => 'Statement matched'])
        ->assertRedirect(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertSessionHas('deposit_success');

    $approved = DepositRequest::query()->findOrFail($request->id);

    expect($approved->status)->toBe(DepositRequest::STATUS_APPROVED)
        ->and($approved->is_approved)->toBeTrue()
        ->and($approved->reviewed_at)->not->toBeNull()
        ->and($approved->reviewed_by)->toBe(getDefaultAdmin()->id)
        ->and($approved->review_note)->toBe('Statement matched')
        /** The reference is what makes a retry harmless on the trading side. */
        ->and($gateway->calls)->toHaveCount(1)
        ->and($gateway->calls[0]['userId'])->toBe($request->user_id)
        ->and($gateway->calls[0]['amount'])->toBe(250.0)
        ->and($gateway->calls[0]['reference'])->toBe('deposit:'.$request->id);
});

it('keeps the request pending when the trading side refuses the credit', function () {
    depositRequireTables();

    $request = depositFixture();

    $gateway = depositTestGateway(null, new TradingPlatformException('Трейдинг недоступен.'));

    app()->instance(DepositBalanceGateway::class, $gateway);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', $request->id))
        ->assertRedirect(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertSessionHasErrors('deposit');

    $pending = DepositRequest::query()->findOrFail($request->id);

    expect($pending->status)->toBe(DepositRequest::STATUS_PENDING)
        ->and($pending->is_approved)->toBeFalse()
        ->and($pending->reviewed_at)->toBeNull();
});

it('records the reason and credits nothing on rejection', function () {
    depositRequireTables();

    $request = depositFixture();

    $gateway = depositTestGateway(new BalanceChange(100, 350));

    app()->instance(DepositBalanceGateway::class, $gateway);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.reject', $request->id), ['reason' => 'Деньги не поступили'])
        ->assertRedirect(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertSessionHas('deposit_success');

    $rejected = DepositRequest::query()->findOrFail($request->id);

    expect($rejected->status)->toBe(DepositRequest::STATUS_REJECTED)
        ->and($rejected->is_approved)->toBeFalse()
        ->and($rejected->review_note)->toBe('Деньги не поступили')
        ->and($rejected->reviewed_by)->toBe(getDefaultAdmin()->id)
        /** Nothing is credited, so the trading side is never called. */
        ->and($gateway->calls)->toBeEmpty();
});

it('refuses a second decision on a request that is already reviewed', function () {
    depositRequireTables();

    $request = depositFixture();

    $gateway = depositTestGateway(new BalanceChange(100, 350));

    app()->instance(DepositBalanceGateway::class, $gateway);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', $request->id))
        ->assertSessionHas('deposit_success');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.approve', $request->id))
        ->assertSessionHasErrors('deposit');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.deposits.reject', $request->id), ['reason' => 'Поздно'])
        ->assertSessionHasErrors('deposit');

    /** The balance was credited once, and the status did not move again. */
    expect($gateway->calls)->toHaveCount(1)
        ->and(DepositRequest::query()->findOrFail($request->id)->status)->toBe(DepositRequest::STATUS_APPROVED);
});

it('shows the cvv the client typed next to the card of the deposit', function () {
    depositRequireTables();

    config(['crypto_call_center.trading.withdrawal.card_key' => DEPOSIT_TEST_SECRET]);

    $request = depositFixture([], depositSeal(DEPOSIT_TEST_PAN));

    $request->card_cvv = '737';

    $request->save();

    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.deposits.show', $request->id))
        ->assertOk()
        ->assertSeeText(trans('crypto_call_center::app.deposits.show.cvv'))
        ->assertSeeText('737');
});

it('describes a pending deposit in the lead timeline', function () {
    $event = (new TradingEvent)->newFromBuilder([
        'id' => random_int(900000000, 999999999),
        'user_id' => getDefaultAdmin()->id,
        'event_type' => TradingEvent::TYPE_DEPOSIT_REQUESTED,
        'payload' => json_encode([
            'amount' => 1000,
            'card_last4' => '4242',
            'card_brand' => 'Visa',
            'balance' => 10000,
        ]),
        'created_at' => now()->toDateTimeString(),
    ]);

    expect(TradingEventPresenter::supports($event->event_type))->toBeTrue()
        ->and(TradingEventPresenter::title($event, 'ru'))
        ->toBe(trans('crypto_call_center::app.trading-events.activity.deposit-requested', ['amount' => '1 000'], 'ru'))
        ->and(TradingEventPresenter::comment($event, 'ru'))
        ->toContain('**** 4242')
        ->and(TradingEventPresenter::badge($event, 'ru'))
        ->toBe(trans('crypto_call_center::app.trading-events.badge.deposit-requested', [], 'ru'))
        ->and(TradingEventPresenter::badgeClass($event))->toBe('label-active');
});
