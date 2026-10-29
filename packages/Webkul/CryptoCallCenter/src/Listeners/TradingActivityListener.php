<?php

namespace Webkul\CryptoCallCenter\Listeners;

use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\CryptoCallCenter\Events\TradingEventReceived;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;
use Webkul\CryptoCallCenter\Services\TradingLeadResolver;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;

/**
 * Writes a trading event into the timeline of the lead it belongs to.
 *
 * A system activity is used on purpose: the timeline already renders the type
 * without the edit and delete menu, so the entry states what happened and
 * nobody can rewrite history by hand.
 */
class TradingActivityListener
{
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected TradingEventRepository $tradingEventRepository,
        protected TradingLeadResolver $leadResolver,
    ) {}

    public function handle(TradingEventReceived $event): void
    {
        $tradingEvent = $event->tradingEvent;

        /**
         * A type this build does not know is parked instead of retried: the row
         * keeps its place in the log and the feed moves on to the next one.
         */
        if (! TradingEventPresenter::supports($tradingEvent->event_type)) {
            $this->tradingEventRepository->markProcessed($tradingEvent->id);

            return;
        }

        /**
         * The ledger entry, the activity and the pivot are one unit: a failure
         * half way rolls the reservation back, so the next run of the feed
         * retries the event instead of losing it.
         */
        DB::transaction(function () use ($tradingEvent): void {
            $lead = $this->leadResolver->resolve($tradingEvent->user_id);

            if (! $lead) {
                return;
            }

            if (! $this->tradingEventRepository->markProcessed($tradingEvent->id, $lead->id)) {
                return;
            }

            $locale = $this->panelLocale();

            $activity = $this->activityRepository->create([
                'title' => TradingEventPresenter::title($tradingEvent, $locale),
                'type' => 'system',
                'comment' => TradingEventPresenter::comment($tradingEvent, $locale),
                'is_done' => 1,
                'user_id' => $tradingEvent->user_id,
            ]);

            $lead->activities()->attach($activity->id);

            $this->tradingEventRepository->attachActivity($tradingEvent->id, $activity->id);
        });
    }

    /**
     * Language the entry is written in.
     *
     * The feed runs from the scheduler, where no `admin_locale` middleware has
     * set a request locale, so the configured panel language is read straight
     * from the system configuration. The text is stored once, in the language
     * the panel opens with, and is not retranslated afterwards.
     */
    protected function panelLocale(): string
    {
        return (string) (core()->getConfigData('general.general.locale_settings.locale')
            ?: app()->getLocale());
    }
}
