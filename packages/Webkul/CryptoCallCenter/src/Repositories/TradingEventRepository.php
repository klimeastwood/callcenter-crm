<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Models\TradingEvent;

/**
 * Read access to the event log the trading application writes, plus the ledger
 * that remembers which rows were already turned into a lead activity.
 *
 * The CRM does not own `trading_events`: both applications share the database,
 * the trading side creates the table and fills it. `trading_event_activities`
 * belongs to the CRM and is the only state kept here.
 */
class TradingEventRepository extends Repository
{
    /**
     * The ledger of processed events.
     */
    public const LEDGER_TABLE = 'trading_event_activities';

    /**
     * The event log written by the trading application.
     */
    public const EVENTS_TABLE = 'trading_events';

    /**
     * Check that the shared log and the CRM ledger exist.
     *
     * A deployment without the trading application has no `trading_events`
     * table, and none of the entry points may fail on a lead page or on the
     * scheduler in that case. The check is deliberately not cached: a queue
     * worker or an Octane process can outlive a migration, and two lookups that
     * MySQL answers from its schema cache are cheaper than a stale `false`.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::EVENTS_TABLE)
            && Schema::hasTable(self::LEDGER_TABLE);
    }

    /**
     * Events no activity has been written for yet, oldest first.
     *
     * The ledger is the source of truth, not the event log: a row is done when
     * it has an entry, whether or not the entry points at an activity (an
     * event of an unknown type is parked without one).
     */
    public function pending(int $limit = 200): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from(self::LEDGER_TABLE)
                    ->whereColumn(self::LEDGER_TABLE.'.trading_event_id', self::EVENTS_TABLE.'.id');
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * The newest events of one trading account, for the lead card block.
     */
    public function latestForUser(int $userId, int $limit = 5): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Reserve an event for this run.
     *
     * The insert is the lock: the primary key of the ledger is the event id, so
     * two schedulers (or a retry that overlaps the previous run) cannot write
     * the event into the same timeline twice. `false` means somebody else got
     * there first and the caller must do nothing.
     */
    public function markProcessed(int $tradingEventId, ?int $leadId = null): bool
    {
        return DB::table(self::LEDGER_TABLE)->insertOrIgnore([
            'trading_event_id' => $tradingEventId,
            'activity_id' => null,
            'lead_id' => $leadId,
            'created_at' => now(),
        ]) === 1;
    }

    /**
     * Point a reserved event at the activity that was written for it.
     */
    public function attachActivity(int $tradingEventId, int $activityId): void
    {
        DB::table(self::LEDGER_TABLE)
            ->where('trading_event_id', $tradingEventId)
            ->update(['activity_id' => $activityId]);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return TradingEvent::class;
    }
}
