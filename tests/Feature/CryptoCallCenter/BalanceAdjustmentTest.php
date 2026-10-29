<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Models\BalanceAdjustment;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;
use Webkul\CryptoCallCenter\Services\Balance\DatabaseTradingBalanceGateway;
use Webkul\CryptoCallCenter\Services\Balance\TradingBalanceGateway;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The manual balance block of the lead card: who may use it, what the CRM
 * records, and how a refusal of the trading side is reported back.
 *
 * The gateway is replaced by a stub wherever the test is about the CRM side of
 * the change - the audit row, the activity, the flash message - so the
 * assertions do not depend on the trading application running next to the test.
 * The two tests that do need the trading tables say so and skip themselves on an
 * installation that has not deployed them.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    /**
     * The block is only offered when the change has somewhere to go, so the
     * transport is pinned instead of inherited from the machine's `.env`.
     */
    config([
        'crypto_call_center.trading.driver' => 'http',
        'crypto_call_center.trading.base_url' => 'http://trading.test',
        'crypto_call_center.trading.token' => 'test-token',
        'crypto_call_center.trading.fallback' => false,
        'crypto_call_center.trading.max_amount' => 1000000,
    ]);
});

/**
 * A lead owned by the given account, on the pipeline the lead page needs.
 */
function balanceTestLead(User $owner): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Balance Lead '.bin2hex(random_bytes(4)),
        'user_id' => $owner->id,
        'lead_value' => 500,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * The virtual account the trading application keeps for one trader.
 */
function balanceTestAccount(User $trader, float $balance): void
{
    DB::table('trading_accounts')->updateOrInsert(
        ['user_id' => $trader->id],
        ['balance_usd' => $balance, 'created_at' => now(), 'updated_at' => now()],
    );
}

/**
 * An employee whose role grants exactly the listed ACL keys.
 *
 * @param  array<int, string>  $permissions
 */
function balanceTestEmployee(array $permissions): User
{
    $role = Role::create([
        'name' => 'balance-role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'Employee '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * A trading side that answers from memory and keeps the request it was given.
 *
 * @param  BalanceChange|null  $change  What the trading side reports back.
 * @param  TradingPlatformException|null  $refusal  What it throws instead.
 */
function balanceTestGateway(?BalanceChange $change = null, ?TradingPlatformException $refusal = null): TradingBalanceGateway
{
    return new class($change, $refusal) implements TradingBalanceGateway
    {
        /** @var array<int, array<string, mixed>> */
        public array $calls = [];

        public function __construct(
            protected ?BalanceChange $change,
            protected ?TradingPlatformException $refusal,
        ) {}

        public function adjust(int $userId, float $amount, string $reason, int $adjustmentId, ?string $operatorName = null): BalanceChange
        {
            $this->calls[] = [
                'userId' => $userId,
                'amount' => $amount,
                'reason' => $reason,
                'adjustmentId' => $adjustmentId,
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
 * The title of the activity the change was written into the timeline as.
 */
function balanceTestActivityTitle(Lead $lead, string $needle): ?string
{
    return Activity::query()
        ->whereHas('leads', fn ($query) => $query->where('leads.id', $lead->id))
        ->where('title', 'like', '%'.$needle.'%')
        ->value('title');
}

it('credits the trader account and keeps the audit trail and the timeline in step', function () {
    useAdminLocale('ru');

    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(new BalanceChange(100, 150));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 50,
            'reason' => 'Welcome bonus',
        ])
        ->assertRedirect(route('admin.leads.view', $lead->id))
        ->assertSessionHas('balance_adjustment_success');

    $adjustment = BalanceAdjustment::query()->where('lead_id', $lead->id)->sole();

    expect((float) $adjustment->amount)->toBe(50.0)
        ->and((float) $adjustment->balance_before)->toBe(100.0)
        ->and((float) $adjustment->balance_after)->toBe(150.0)
        ->and($adjustment->admin_id)->toBe($operator->id)
        ->and($adjustment->user_id)->toBe((int) $lead->user_id)
        ->and($adjustment->reason)->toBe('Welcome bonus');

    /**
     * The id of the CRM row travels as the idempotency key: a retry whose
     * response was lost must not credit the same change twice.
     */
    expect($gateway->calls)->toHaveCount(1)
        ->and($gateway->calls[0]['adjustmentId'])->toBe((int) $adjustment->id)
        ->and($gateway->calls[0]['amount'])->toBe(50.0)
        ->and($gateway->calls[0]['userId'])->toBe((int) $lead->user_id);

    expect(balanceTestActivityTitle($lead, 'Welcome bonus'))->not->toBeNull()
        ->and(balanceTestActivityTitle($lead, TradingEventPresenter::signedMoney(50)))->not->toBeNull();
});

it('records a write-off as a negative amount', function () {
    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(new BalanceChange(500, 475));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => -25,
            'reason' => 'Chargeback',
        ])
        ->assertRedirect(route('admin.leads.view', $lead->id));

    $adjustment = BalanceAdjustment::query()->where('lead_id', $lead->id)->sole();

    expect((float) $adjustment->amount)->toBe(-25.0)
        ->and($gateway->calls[0]['amount'])->toBe(-25.0)
        ->and(TradingEventPresenter::signedMoney($adjustment->amount))->toBe('-$25');
});

it('refuses a zero amount before the request reaches the trading side', function () {
    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(new BalanceChange(100, 100));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 0,
            'reason' => 'Nothing at all',
        ])
        ->assertSessionHasErrors('amount');

    expect($gateway->calls)->toBe([])
        ->and(BalanceAdjustment::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('refuses an amount above the ceiling of the trading side', function () {
    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(new BalanceChange(0, 1000001));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 1000001,
            'reason' => 'Too much',
        ])
        ->assertSessionHasErrors('amount');

    expect($gateway->calls)->toBe([]);
});

it('refuses a manager who may move money but does not own the lead', function () {
    $lead = balanceTestLead(getDefaultAdmin());

    $manager = balanceTestEmployee([
        'leads',
        'leads.view',
        'crypto_call_center.balance',
    ]);

    $gateway = balanceTestGateway(new BalanceChange(0, 100));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($manager, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 10,
            'reason' => 'Bonus',
        ])
        ->assertForbidden();

    expect($gateway->calls)->toBe([])
        ->and(BalanceAdjustment::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('refuses the owner when the role does not grant the balance permission', function () {
    $operator = balanceTestEmployee(['leads', 'leads.view']);

    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(new BalanceChange(0, 100));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 10,
            'reason' => 'Bonus',
        ])
        ->assertUnauthorized();

    /**
     * `Config/acl.php` maps this route to the ACL key, and the core bouncer
     * answers a missing key with 401 before the request is even validated.
     */
    expect($gateway->calls)->toBe([])
        ->and(BalanceAdjustment::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('removes the audit row when the trading side refuses the change', function () {
    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    $gateway = balanceTestGateway(null, new TradingPlatformException('The balance would become negative.'));

    app()->instance(TradingBalanceGateway::class, $gateway);

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => -1000,
            'reason' => 'Write-off',
        ])
        ->assertRedirect(route('admin.leads.view', $lead->id))
        ->assertSessionHasErrors('balance_adjustment');

    /** The history never shows a change the trading side did not perform. */
    expect(BalanceAdjustment::query()->where('lead_id', $lead->id)->count())->toBe(0)
        ->and($gateway->calls)->toHaveCount(1);
});

it('applies the same CRM adjustment to the shared tables only once', function () {
    if (! DatabaseTradingBalanceGateway::available()) {
        $this->markTestSkipped('The trading tables are not deployed on this installation.');
    }

    $trader = getDefaultAdmin();

    balanceTestAccount($trader, 100);

    $reference = random_int(500000000, 999999999);

    $gateway = app(DatabaseTradingBalanceGateway::class);

    $first = $gateway->adjust($trader->id, 25, 'Bonus', $reference);

    /**
     * A retry carries the same CRM row id, which is the reference of the event
     * that was already written: the balance must not move a second time.
     */
    $second = $gateway->adjust($trader->id, 25, 'Bonus', $reference);

    $balance = (float) DB::table(DatabaseTradingBalanceGateway::ACCOUNTS_TABLE)
        ->where('user_id', $trader->id)
        ->value('balance_usd');

    $events = DB::table(DatabaseTradingBalanceGateway::EVENTS_TABLE)
        ->where('event_type', TradingEvent::TYPE_BALANCE_ADJUSTMENT)
        ->where('payload->reference', (string) $reference)
        ->count();

    expect($first->applied)->toBeTrue()
        ->and($second->applied)->toBeFalse()
        ->and($second->tradingEventId)->toBe($first->tradingEventId)
        ->and($balance)->toBe(125.0)
        ->and($events)->toBe(1);
});

it('marks the mirrored trading event so the feed does not write it again', function () {
    if (! DatabaseTradingBalanceGateway::available()) {
        $this->markTestSkipped('The trading tables are not deployed on this installation.');
    }

    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    balanceTestAccount($operator, 100);

    app()->instance(TradingBalanceGateway::class, app(DatabaseTradingBalanceGateway::class));

    $this->actingAs($operator, 'user')
        ->from(route('admin.leads.view', $lead->id))
        ->post(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), [
            'amount' => 40,
            'reason' => 'Deposit match',
        ])
        ->assertRedirect(route('admin.leads.view', $lead->id));

    $adjustment = BalanceAdjustment::query()->where('lead_id', $lead->id)->sole();

    $ledger = DB::table('trading_event_activities')
        ->where('trading_event_id', $adjustment->trading_event_id)
        ->first();

    /**
     * The reservation carries the id of the operator's own activity: the
     * scheduler reads the ledger and skips an event somebody already wrote.
     */
    expect((float) $adjustment->balance_before)->toBe(100.0)
        ->and((float) $adjustment->balance_after)->toBe(140.0)
        ->and($adjustment->trading_event_id)->not->toBeNull()
        ->and($ledger)->not->toBeNull()
        ->and($ledger->activity_id)->not->toBeNull()
        ->and(Activity::query()->whereKey($ledger->activity_id)->where('title', 'like', '%Deposit match%')->exists())->toBeTrue();
});

it('shows the balance block on the card of the lead owner', function () {
    useAdminLocale('ru');

    $operator = getDefaultAdmin();
    $lead = balanceTestLead($operator);

    balanceTestAccount($operator, 250.5);

    $this->actingAs($operator, 'user')
        ->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.balance-adjustments.title', [], 'ru'), false)
        ->assertSee(trans('crypto_call_center::app.balance-adjustments.current-balance', [], 'ru'), false)
        ->assertSee('$250.50', false)
        ->assertSee(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), false);
});

it('hides the balance block from an employee who does not own the lead', function () {
    useAdminLocale('ru');

    $lead = balanceTestLead(getDefaultAdmin());

    $manager = balanceTestEmployee([
        'leads',
        'leads.view',
        'crypto_call_center.balance',
    ]);

    balanceTestAccount($manager, 250.5);

    $this->actingAs($manager, 'user')
        ->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertDontSee(trans('crypto_call_center::app.balance-adjustments.title', [], 'ru'), false)
        ->assertDontSee(route('admin.crypto_call_center.balance_adjustments.store', $lead->id), false);
});
