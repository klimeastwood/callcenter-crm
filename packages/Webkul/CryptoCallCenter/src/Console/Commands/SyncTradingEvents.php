<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Illuminate\Console\Command;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;
use Webkul\CryptoCallCenter\Services\TradingEventFeed;

/**
 * Turns the new rows of the shared trading log into lead activities.
 *
 * The trading application writes into the same database but runs in its own
 * process, so it cannot raise an event inside the CRM. Reading the log on a
 * schedule is the bridge: the ledger keeps the run idempotent and the log
 * itself is never modified.
 */
class SyncTradingEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto-call-center:sync-trading-events
                            {--limit=200 : How many unprocessed events to read in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write the new trading events into the timelines of the leads they belong to';

    /**
     * Execute the console command.
     */
    public function handle(TradingEventFeed $feed): int
    {
        if (! TradingEventRepository::available()) {
            $this->warn('The trading_events table is missing: install the trading application or run its migrations first.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->info('Read '.$feed->dispatchPending($limit).' trading event(s).');

        return self::SUCCESS;
    }
}
