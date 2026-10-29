<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\CryptoCallCenter\Contracts\TradingEvent as TradingEventContract;
use Webkul\CryptoCallCenter\Events\TradingEventReceived;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;
use Webkul\CryptoCallCenter\Services\TradingEventFeed;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * An event row as the trading application writes it.
 *
 * The feed reads the shared `trading_events` table, but the listener only needs
 * the hydrated model. Building it here keeps the tests independent of how many
 * real events the shared database carries and of whether the trading migrations
 * were run on this machine at all.
 *
 * @param  array<string, mixed>  $attributes
 */
function tradingEventModel(array $attributes = []): TradingEvent
{
    return (new TradingEvent)->newFromBuilder(array_merge([
        'id' => random_int(900000000, 999999999),
        'user_id' => getDefaultAdmin()->id,
        'event_type' => TradingEvent::TYPE_DEPOSIT,
        'payload' => json_encode(['amount' => 100]),
        'created_at' => now()->toDateTimeString(),
    ], $attributes));
}

/**
 * A lead owned by the given account.
 */
function tradingLeadFor(User $user): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Trading Lead '.bin2hex(random_bytes(4)),
        'user_id' => $user->id,
        'lead_value' => 500,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * An account that exists but owns no lead, e.g. a trader who has not been
 * handed to a manager yet.
 */
function tradingOperatorWithoutLead(): User
{
    return User::create([
        'name' => 'trader-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => getDefaultAdmin()->role_id,
    ]);
}

/**
 * The activity the ledger points at, if the event was turned into one.
 *
 * The lookup goes through the ledger on purpose: creating a lead already writes
 * its own "Created" activity into the timeline, so the newest activity of a
 * card is not necessarily the one a trading event produced.
 */
function tradingActivityOf(int $tradingEventId): ?Activity
{
    $activityId = DB::table('trading_event_activities')
        ->where('trading_event_id', $tradingEventId)
        ->value('activity_id');

    return $activityId ? Activity::find($activityId) : null;
}

it('writes a deposit into the timeline of the owning lead', function () {
    /**
     * The text is stored once, in the language the panel opens with. Pinning
     * the configuration row makes the assertion independent of whatever the
     * test database happens to carry.
     */
    useAdminLocale('ru');

    $user = getDefaultAdmin();
    $lead = tradingLeadFor($user);

    event($event = new TradingEventReceived(tradingEventModel([
        'user_id' => $user->id,
        'payload' => json_encode([
            'amount' => 1000,
            'balance_before' => 500,
            'balance_after' => 1500,
        ]),
    ])));

    $activity = tradingActivityOf($event->tradingEvent->id);

    expect($activity)->not->toBeNull()
        ->and($activity->type)->toBe('system')
        ->and($activity->is_done)->toBe(1)
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->title)->toBe(
            trans('crypto_call_center::app.trading-events.activity.deposit', ['amount' => '1 000'], 'ru')
        )
        ->and($activity->comment)->toBe(
            trans('crypto_call_center::app.trading-events.activity.balance', ['before' => '500', 'after' => '1 500'], 'ru')
        );
});

it('describes an opened short position with its volume and entry price', function () {
    useAdminLocale('ru');

    tradingLeadFor(getDefaultAdmin());

    event($event = new TradingEventReceived(tradingEventModel([
        'event_type' => TradingEvent::TYPE_POSITION_OPENED,
        'payload' => json_encode([
            'symbol' => 'BTCUSDT',
            'side' => 'sell',
            'amount' => 0.5,
            'entry_price' => 60000,
            'balance_after' => 1200,
        ]),
    ])));

    $activity = tradingActivityOf($event->tradingEvent->id);

    expect($activity->title)->toBe(
        trans('crypto_call_center::app.trading-events.activity.short', ['symbol' => 'BTCUSDT', 'amount' => '30 000'], 'ru')
    )
        ->and($activity->comment)->toBe(implode('; ', [
            trans('crypto_call_center::app.trading-events.activity.volume', ['amount' => '0.5', 'symbol' => 'BTCUSDT'], 'ru'),
            trans('crypto_call_center::app.trading-events.activity.entry', ['price' => '60 000'], 'ru'),
            trans('crypto_call_center::app.trading-events.activity.balance-after', ['balance' => '1 200'], 'ru'),
        ]));
});

it('describes a closed position by the sign of the result', function () {
    useAdminLocale('ru');

    tradingLeadFor(getDefaultAdmin());

    event($event = new TradingEventReceived(tradingEventModel([
        'event_type' => TradingEvent::TYPE_POSITION_CLOSED,
        'payload' => json_encode([
            'symbol' => 'ETHUSDT',
            'pnl' => -250.5,
            'entry_price' => 2000,
            'close_price' => 1900,
            'balance_after' => 800,
        ]),
    ])));

    $activity = tradingActivityOf($event->tradingEvent->id);

    expect($activity->title)->toBe(
        trans('crypto_call_center::app.trading-events.activity.closed-loss', ['symbol' => 'ETHUSDT', 'amount' => '250.50'], 'ru')
    )
        ->and($activity->comment)->toBe(implode('; ', [
            trans('crypto_call_center::app.trading-events.activity.prices', ['entry' => '2 000', 'close' => '1 900'], 'ru'),
            trans('crypto_call_center::app.trading-events.activity.balance-after', ['balance' => '800'], 'ru'),
        ]));
});

it('writes an event only once however often it is delivered', function () {
    $lead = tradingLeadFor(getDefaultAdmin());

    $event = tradingEventModel();

    event(new TradingEventReceived($event));
    event(new TradingEventReceived($event));

    $activityId = DB::table('trading_event_activities')->where('trading_event_id', $event->id)->value('activity_id');

    expect(DB::table('trading_event_activities')->where('trading_event_id', $event->id)->count())->toBe(1)
        ->and($activityId)->not->toBeNull()
        ->and($lead->activities()->whereKey($activityId)->count())->toBe(1);
});

it('writes the event into the newest lead of the account', function () {
    $user = getDefaultAdmin();

    $older = tradingLeadFor($user);
    $newer = tradingLeadFor($user);

    $event = tradingEventModel(['user_id' => $user->id]);

    event(new TradingEventReceived($event));

    /**
     * Creating a lead writes a "Created" activity of its own, so the ledger is
     * what proves which card received the trade.
     */
    $leadId = DB::table('trading_event_activities')->where('trading_event_id', $event->id)->value('lead_id');

    expect($leadId)->toBe($newer->id)
        ->and($leadId)->not->toBe($older->id);
});

it('parks an unknown event type instead of retrying it forever', function () {
    tradingLeadFor(getDefaultAdmin());

    /** `withdrawal.requested` is a known type now, so the sample stays outside `TYPES`. */
    $event = tradingEventModel(['event_type' => 'kyc.requested']);

    event(new TradingEventReceived($event));

    $ledger = DB::table('trading_event_activities')->where('trading_event_id', $event->id)->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->activity_id)->toBeNull();

    /** A second delivery is a no-op, not another parked row. */
    event(new TradingEventReceived($event));

    expect(DB::table('trading_event_activities')->where('trading_event_id', $event->id)->count())->toBe(1);
});

it('keeps an event of an account without a lead in the queue', function () {
    $operator = tradingOperatorWithoutLead();

    $event = tradingEventModel(['user_id' => $operator->id]);

    event(new TradingEventReceived($event));

    /** No reservation was written, so the next run of the feed retries it. */
    expect(DB::table('trading_event_activities')->where('trading_event_id', $event->id)->exists())->toBeFalse();
});

it('turns the pending rows of the log into activities', function () {
    $lead = tradingLeadFor(getDefaultAdmin());

    /**
     * The feed is tested against a stub instead of the shared table: the
     * assertion is about the wiring - feed dispatches the event, the provider
     * maps it to the listener - not about how many real rows are waiting.
     */
    $repository = new class(app()) extends TradingEventRepository
    {
        /** @var array<int, TradingEvent> */
        public array $pendingEvents = [];

        public function pending(int $limit = 200): Collection
        {
            return new Collection($this->pendingEvents);
        }
    };

    $repository->pendingEvents = [$model = tradingEventModel()];

    app()->instance(TradingEventRepository::class, $repository);

    expect(app(TradingEventFeed::class)->dispatchPending())->toBe(1);

    $activityId = DB::table('trading_event_activities')->where('trading_event_id', $model->id)->value('activity_id');

    expect($activityId)->not->toBeNull()
        ->and($lead->activities()->whereKey($activityId)->count())->toBe(1);
});

it('resolves the event model through its contract', function () {
    /** Concord binds the contract to the model, so no consumer names the class. */
    expect(app(TradingEventContract::class))->toBeInstanceOf(TradingEvent::class);
});

it('shows the latest trading events on the lead card', function () {
    if (! Schema::hasTable('trading_events')) {
        $this->markTestSkipped('The shared `trading_events` table is not deployed on this installation.');
    }

    useAdminLocale('ru');

    $user = getDefaultAdmin();
    $lead = tradingLeadFor($user);

    DB::table('trading_events')->insert([
        'user_id' => $user->id,
        'event_type' => TradingEvent::TYPE_DEPOSIT,
        'payload' => json_encode([
            'amount' => 1000,
            'balance_before' => 500,
            'balance_after' => 1500,
        ]),
        'created_at' => now(),
    ]);

    $this->actingAs($user, 'user')
        ->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.trading-events.title', [], 'ru'), false)
        ->assertSee(trans('crypto_call_center::app.trading-events.activity.deposit', ['amount' => '1 000'], 'ru'), false)
        ->assertSee(trans('crypto_call_center::app.trading-events.badge.deposit', [], 'ru'), false);
});

it('formats the amounts the panel reads', function () {
    expect(TradingEventPresenter::money(1000))->toBe('1 000')
        ->and(TradingEventPresenter::money(250.5))->toBe('250.50')
        ->and(TradingEventPresenter::money(null))->toBe('0')
        ->and(TradingEventPresenter::money(1234567.891))->toBe('1 234 567.89');
});
