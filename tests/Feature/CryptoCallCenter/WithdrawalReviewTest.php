<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Exceptions\WithdrawalReviewException;
use Webkul\CryptoCallCenter\Models\WithdrawalCard;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;
use Webkul\CryptoCallCenter\Repositories\WithdrawalRequestRepository;
use Webkul\CryptoCallCenter\Services\Withdrawal\DatabaseWithdrawalReviewGateway;
use Webkul\CryptoCallCenter\Services\Withdrawal\HttpWithdrawalReviewGateway;
use Webkul\CryptoCallCenter\Services\Withdrawal\WithdrawalReviewGateway;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The payout desk: approve and reject of a withdrawal request, and the two
 * transports that write the decision.
 *
 * A payout belongs to the trading application, so the shared table is written
 * through the bridge first. The requests that need rows skip themselves on an
 * installation that has not deployed the trading tables; the bridge itself is
 * replaced by `Http::fake()` or by a stub, so the suite never calls a live
 * trading application.
 */
uses(DatabaseTransactions::class);

/**
 * Skip the payout checks on an installation without the trading tables.
 */
function payoutReviewRequireTables(): void
{
    if (! WithdrawalRequestRepository::available()) {
        test()->markTestSkipped('The trading `withdrawal_requests` table is not deployed on this installation.');
    }
}

/**
 * A client of the trading cabinet.
 */
function payoutReviewClient(): User
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
function payoutReviewEmployee(array $permissions): User
{
    $role = Role::create([
        'name' => 'payout-role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'payout-employee-'.uniqid(),
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
function payoutReviewSeal(string $digits, string $secret): string
{
    $key = hash('sha256', $secret, true);

    $nonce = random_bytes(12);

    $tag = '';

    $ciphertext = openssl_encrypt($digits, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

    return 'v1:'.base64_encode($nonce).':'.base64_encode($tag).':'.base64_encode((string) $ciphertext);
}

/**
 * A pending payout of a fresh client, with the card it should be paid to.
 *
 * @param  array<string, mixed>  $overrides
 */
function payoutReviewFixture(array $overrides = [], ?string $panEncrypted = null): WithdrawalRequest
{
    $client = payoutReviewClient();

    $card = WithdrawalCard::create([
        'user_id' => $client->id,
        'holder_name' => 'IVAN IVANOV',
        'brand' => 'visa',
        'last4' => '4242',
        'expiry' => '12/30',
        'pan_encrypted' => $panEncrypted ?? '',
        'fingerprint' => bin2hex(random_bytes(16)),
    ]);

    return WithdrawalRequest::create(array_merge([
        'user_id' => $client->id,
        'withdrawal_card_id' => $card->id,
        'amount_usd' => 250,
        'method' => WithdrawalRequest::METHOD_CARD,
        'status' => WithdrawalRequest::STATUS_PENDING,
        'is_approved' => false,
    ], $overrides));
}

/**
 * A gateway that records what the service asked it to do.
 */
function payoutReviewStub(?bool $applied = true, ?TradingPlatformException $refusal = null): WithdrawalReviewGateway
{
    return new class($applied ?? true, $refusal) implements WithdrawalReviewGateway
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $calls = [];

        public function __construct(
            protected bool $applied,
            protected ?TradingPlatformException $refusal,
        ) {}

        public function decide(int $requestId, string $status, ?string $note, ?int $reviewerId): bool
        {
            $this->calls[] = [
                'requestId' => $requestId,
                'status' => $status,
                'note' => $note,
                'reviewerId' => $reviewerId,
            ];

            if ($this->refusal) {
                throw $this->refusal;
            }

            return $this->applied;
        }
    };
}

/**
 * A gateway that stands in for the trading side when a payout is closed: it
 * records the call and writes the status the trading application would store.
 */
function payoutReviewCompletingGateway(): WithdrawalReviewGateway
{
    return new class implements WithdrawalReviewGateway
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $calls = [];

        public function decide(int $requestId, string $status, ?string $note, ?int $reviewerId): bool
        {
            $this->calls[] = [
                'requestId' => $requestId,
                'status' => $status,
                'note' => $note,
                'reviewerId' => $reviewerId,
            ];

            WithdrawalRequest::query()->whereKey($requestId)->update([
                'status' => $status,
                'is_approved' => in_array($status, [
                    WithdrawalRequest::STATUS_APPROVED,
                    WithdrawalRequest::STATUS_PAID,
                ], true),
            ]);

            return true;
        }
    };
}

it('records the approval through the gateway and passes the reviewer', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    $gateway = payoutReviewStub();

    app()->instance(WithdrawalReviewGateway::class, $gateway);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.approve', $request->id), ['note' => 'Проверено'])
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHas('withdrawal_success');

    expect($gateway->calls)->toHaveCount(1)
        ->and($gateway->calls[0]['requestId'])->toBe($request->id)
        ->and($gateway->calls[0]['status'])->toBe(WithdrawalRequest::STATUS_APPROVED)
        ->and($gateway->calls[0]['note'])->toBe('Проверено')
        ->and($gateway->calls[0]['reviewerId'])->toBe(getDefaultAdmin()->id);
});

it('writes the approval into the shared table when the database is the transport', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        app(DatabaseWithdrawalReviewGateway::class)
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.approve', $request->id))
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHas('withdrawal_success');

    $approved = WithdrawalRequest::query()->findOrFail($request->id);

    expect($approved->status)->toBe(WithdrawalRequest::STATUS_APPROVED)
        ->and($approved->is_approved)->toBeTrue()
        ->and($approved->reviewed_by)->toBe(getDefaultAdmin()->id)
        ->and($approved->reviewed_at)->not->toBeNull();
});

it('rejects a payout with the reason the client reads', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        app(DatabaseWithdrawalReviewGateway::class)
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.reject', $request->id), ['reason' => 'Карта не принадлежит клиенту'])
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHas('withdrawal_success');

    $rejected = WithdrawalRequest::query()->findOrFail($request->id);

    expect($rejected->status)->toBe(WithdrawalRequest::STATUS_REJECTED)
        ->and($rejected->is_approved)->toBeFalse()
        ->and($rejected->review_note)->toBe('Карта не принадлежит клиенту');
});

it('prints the operator and the reason once the payout is decided', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        app(DatabaseWithdrawalReviewGateway::class)
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.reject', $request->id), ['reason' => 'Карта не принадлежит клиенту'])
        ->assertSessionHas('withdrawal_success');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSeeText(trans('crypto_call_center::app.withdrawals.card.reviewed-by'))
        ->assertSeeText(getDefaultAdmin()->name)
        ->assertSeeText('Карта не принадлежит клиенту')
        ->assertDontSee(route('admin.crypto_call_center.withdrawals.approve', $request->id), false)
        ->assertDontSee(route('admin.crypto_call_center.withdrawals.reject', $request->id), false);
});

it('refuses a second decision on a request that is already reviewed', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        app(DatabaseWithdrawalReviewGateway::class)
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.approve', $request->id))
        ->assertSessionHas('withdrawal_success');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.approve', $request->id))
        ->assertSessionHasErrors('withdrawal');

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.reject', $request->id), ['reason' => 'Поздно'])
        ->assertSessionHasErrors('withdrawal');

    expect(WithdrawalRequest::query()->findOrFail($request->id)->status)
        ->toBe(WithdrawalRequest::STATUS_APPROVED);
});

it('keeps the payout pending when the trading side cannot be reached', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        payoutReviewStub(null, new TradingPlatformException('Трейдинг недоступен.'))
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.approve', $request->id))
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHasErrors('withdrawal');

    $pending = WithdrawalRequest::query()->findOrFail($request->id);

    expect($pending->status)->toBe(WithdrawalRequest::STATUS_PENDING)
        ->and($pending->is_approved)->toBeFalse()
        ->and($pending->reviewed_at)->toBeNull();
});

it('sends the decision to the bridge endpoint', function () {
    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
    ]);

    Http::fake(['trading.test/*' => Http::response(['applied' => true], 200)]);

    expect(app(HttpWithdrawalReviewGateway::class)
        ->decide(17, WithdrawalRequest::STATUS_APPROVED, 'Проверено', 3))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://trading.test/api/withdrawals/review'
            && $request->method() === 'POST'
            && $request['request_id'] === 17
            && $request['status'] === WithdrawalRequest::STATUS_APPROVED
            && $request['note'] === 'Проверено'
            && $request['reviewer_id'] === 3
            && $request->hasHeader('Authorization', 'Bearer bridge-token');
    });
});

it('treats a repeated delivery as an already decided request', function () {
    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
    ]);

    Http::fake(['trading.test/*' => Http::response(['applied' => false], 200)]);

    expect(app(HttpWithdrawalReviewGateway::class)
        ->decide(17, WithdrawalRequest::STATUS_REJECTED, null, 3))->toBeFalse();
});

it('shows the refusal of the trading side word for word', function () {
    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
        'crypto_call_center.trading.fallback' => true,
    ]);

    Http::fake(['trading.test/*' => Http::response(['message' => 'Заявка уже одобрена.'], 422)]);

    expect(fn () => app(HttpWithdrawalReviewGateway::class)
        ->decide(17, WithdrawalRequest::STATUS_REJECTED, null, 3))
        ->toThrow(TradingPlatformException::class, 'Заявка уже одобрена.');
});

it('reports an unreachable bridge when the fallback is disabled', function () {
    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
        'crypto_call_center.trading.fallback' => false,
    ]);

    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => app(HttpWithdrawalReviewGateway::class)
        ->decide(17, WithdrawalRequest::STATUS_APPROVED, null, 3))
        ->toThrow(TradingPlatformException::class, trans('crypto_call_center::app.withdrawals.errors.unreachable'));
});

it('falls back to the shared table when the bridge fails and the fallback is allowed', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
        'crypto_call_center.trading.fallback' => true,
    ]);

    Http::fake(['trading.test/*' => Http::response(['message' => 'Server error'], 503)]);

    expect(app(HttpWithdrawalReviewGateway::class)
        ->decide($request->id, WithdrawalRequest::STATUS_APPROVED, null, getDefaultAdmin()->id))->toBeTrue();

    expect(WithdrawalRequest::query()->findOrFail($request->id)->status)
        ->toBe(WithdrawalRequest::STATUS_APPROVED);
});

it('refuses to close a payout in the shared table when the bridge is unavailable', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture([
        'status' => WithdrawalRequest::STATUS_APPROVED,
        'is_approved' => true,
        'reviewed_by' => getDefaultAdmin()->id,
        'reviewed_at' => now(),
    ]);

    config([
        'crypto_call_center.trading.base_url' => 'https://trading.test',
        'crypto_call_center.trading.token' => 'bridge-token',
        'crypto_call_center.trading.fallback' => true,
    ]);

    Http::fake(['trading.test/*' => Http::response(['message' => 'Server error'], 503)]);

    expect(fn () => app(HttpWithdrawalReviewGateway::class)
        ->decide($request->id, WithdrawalRequest::STATUS_PAID, null, null))
        ->toThrow(
            WithdrawalReviewException::class,
            trans('crypto_call_center::app.withdrawals.errors.requires-bridge')
        );

    $unchanged = WithdrawalRequest::query()->findOrFail($request->id);

    expect($unchanged->status)->toBe(WithdrawalRequest::STATUS_APPROVED)
        ->and($unchanged->isApproved())->toBeTrue();
});

it('shows the cvv the client typed next to the card of the payout', function () {
    payoutReviewRequireTables();

    $secret = 'payout-review-secret';

    config(['crypto_call_center.trading.withdrawal.card_key' => $secret]);

    $request = payoutReviewFixture([], payoutReviewSeal('4242424242424242', $secret));

    $request->card_cvv = '737';

    $request->save();

    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSeeText(trans('crypto_call_center::app.withdrawals.card.cvv'))
        ->assertSeeText('737');
});

it('explains that a payout can only be closed through the bridge', function () {
    payoutReviewRequireTables();

    config([
        'crypto_call_center.trading.driver' => 'http',
        'crypto_call_center.trading.base_url' => '',
        'crypto_call_center.trading.token' => '',
    ]);

    $request = payoutReviewFixture([
        'status' => WithdrawalRequest::STATUS_APPROVED,
        'is_approved' => true,
        'reviewed_by' => getDefaultAdmin()->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSeeText(trans('crypto_call_center::app.withdrawals.errors.requires-bridge'))
        ->assertDontSeeText(trans('crypto_call_center::app.withdrawals.review.complete-title'))
        ->assertDontSee(route('admin.crypto_call_center.withdrawals.complete', $request->id), false);
});

it('closes an approved payout as paid and keeps the approval on record', function () {
    payoutReviewRequireTables();

    $reviewer = getDefaultAdmin();

    $request = payoutReviewFixture([
        'status' => WithdrawalRequest::STATUS_APPROVED,
        'is_approved' => true,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
        'review_note' => 'Проверено по карте',
    ]);

    $approvedAt = $request->reviewed_at;

    $gateway = payoutReviewCompletingGateway();

    app()->instance(WithdrawalReviewGateway::class, $gateway);

    $this->actingAs($reviewer, 'user')
        ->post(route('admin.crypto_call_center.withdrawals.complete', $request->id))
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHas('withdrawal_success');

    expect($gateway->calls)->toHaveCount(1)
        ->and($gateway->calls[0]['status'])->toBe(WithdrawalRequest::STATUS_PAID)
        ->and($gateway->calls[0]['reviewerId'])->toBeNull();

    $completed = WithdrawalRequest::query()->findOrFail($request->id);

    expect($completed->status)->toBe(WithdrawalRequest::STATUS_PAID)
        ->and($completed->is_approved)->toBeTrue()
        ->and($completed->isCompleted())->toBeTrue()
        ->and($completed->reviewed_by)->toBe($reviewer->id)
        ->and($completed->review_note)->toBe('Проверено по карте')
        ->and($completed->reviewed_at->equalTo($approvedAt))->toBeTrue();

    /** The page confirms the closed payout instead of offering another decision. */
    $this->actingAs($reviewer, 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertOk()
        ->assertSeeText(trans('crypto_call_center::app.withdrawals.card.completed'))
        ->assertDontSee(route('admin.crypto_call_center.withdrawals.complete', $request->id), false)
        ->assertDontSee(route('admin.crypto_call_center.withdrawals.approve', $request->id), false);
});

it('answers a repeated completion without writing the payout twice', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture([
        'status' => WithdrawalRequest::STATUS_APPROVED,
        'is_approved' => true,
        'reviewed_by' => getDefaultAdmin()->id,
        'reviewed_at' => now(),
    ]);

    $gateway = payoutReviewCompletingGateway();

    app()->instance(WithdrawalReviewGateway::class, $gateway);

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->actingAs(getDefaultAdmin(), 'user')
            ->post(route('admin.crypto_call_center.withdrawals.complete', $request->id))
            ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
            ->assertSessionHas('withdrawal_success');
    }

    expect($gateway->calls)->toHaveCount(1)
        ->and(WithdrawalRequest::query()->findOrFail($request->id)->status)
        ->toBe(WithdrawalRequest::STATUS_PAID);
});

it('refuses to complete a payout that was not approved', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture();

    app()->instance(
        WithdrawalReviewGateway::class,
        app(DatabaseWithdrawalReviewGateway::class)
    );

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.crypto_call_center.withdrawals.complete', $request->id))
        ->assertRedirect(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertSessionHasErrors('withdrawal');

    $pending = WithdrawalRequest::query()->findOrFail($request->id);

    expect($pending->status)->toBe(WithdrawalRequest::STATUS_PENDING)
        ->and($pending->is_approved)->toBeFalse()
        ->and($pending->reviewed_at)->toBeNull();
});

it('hides the completion action and refuses it without the payout-desk permission', function () {
    payoutReviewRequireTables();

    $request = payoutReviewFixture([
        'status' => WithdrawalRequest::STATUS_APPROVED,
        'is_approved' => true,
        'reviewed_by' => getDefaultAdmin()->id,
        'reviewed_at' => now(),
        'review_note' => 'Проверено по карте',
    ]);

    $reader = payoutReviewEmployee(['withdrawals']);

    $this->actingAs($reader, 'user')
        ->get(route('admin.crypto_call_center.withdrawals.card', $request->id))
        ->assertUnauthorized();

    $this->actingAs($reader, 'user')
        ->post(route('admin.crypto_call_center.withdrawals.complete', $request->id))
        ->assertUnauthorized();

    expect(WithdrawalRequest::query()->findOrFail($request->id)->status)
        ->toBe(WithdrawalRequest::STATUS_APPROVED);
});
