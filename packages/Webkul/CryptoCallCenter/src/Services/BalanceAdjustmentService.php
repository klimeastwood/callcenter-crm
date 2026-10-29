<?php

namespace Webkul\CryptoCallCenter\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Models\BalanceAdjustment;
use Webkul\CryptoCallCenter\Repositories\BalanceAdjustmentRepository;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;
use Webkul\CryptoCallCenter\Services\Balance\TradingBalanceGateway;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * One manual balance change, from the CRM row to the lead timeline.
 *
 * The order is deliberate: the CRM row is written first, because its id is the
 * idempotency key sent to the trading side. If the change is refused, the row
 * is removed again, so the history never shows a change that did not happen.
 * The activity and the ledger reservation are one transaction: either the
 * operator sees the entry and the scheduler skips the mirrored event, or
 * neither happened.
 */
class BalanceAdjustmentService
{
    public function __construct(
        protected BalanceAdjustmentRepository $adjustments,
        protected TradingEventRepository $tradingEvents,
        protected TradingBalanceGateway $gateway,
        protected ActivityRepository $activities,
    ) {}

    /**
     * Apply a signed amount to the trader account of the lead.
     *
     * @param  float  $amount  Positive for a bonus, negative for a write-off.
     *
     * @throws TradingPlatformException
     */
    public function adjust(Lead $lead, User $operator, float $amount, string $reason): BalanceAdjustment
    {
        $userId = (int) $lead->user_id;

        if ($userId === 0) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.no-account')
            );
        }

        $amount = round($amount, 2);

        $reason = trim($reason);

        $adjustment = $this->adjustments->create([
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'admin_id' => $operator->id,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        try {
            $change = $this->gateway->adjust(
                $userId,
                $amount,
                $reason,
                (int) $adjustment->id,
                $operator->name,
            );
        } catch (TradingPlatformException $exception) {
            $adjustment->delete();

            throw $exception;
        }

        DB::transaction(function () use ($adjustment, $change, $lead, $userId, $amount, $reason): void {
            $adjustment->forceFill([
                'balance_before' => $change->balanceBefore,
                'balance_after' => $change->balanceAfter,
                'trading_event_id' => $change->tradingEventId,
            ])->save();

            $locale = app()->getLocale();

            $activity = $this->activities->create([
                'title' => trans('crypto_call_center::app.balance-adjustments.activity.title', [
                    'amount' => TradingEventPresenter::signedMoney($amount),
                    'reason' => $reason,
                ], $locale),
                'type' => 'system',
                'comment' => trans('crypto_call_center::app.trading-events.activity.balance', [
                    'before' => TradingEventPresenter::money($change->balanceBefore),
                    'after' => TradingEventPresenter::money($change->balanceAfter),
                ], $locale),
                'is_done' => 1,
                'user_id' => $userId,
            ]);

            $lead->activities()->attach($activity->id);

            /**
             * The change is mirrored into the shared event log as well, and the
             * scheduler writes every unprocessed event into the timeline. The
             * reservation keeps the operator's own entry the only one: when the
             * feed already wrote its own entry, its row wins and the link is
             * left untouched.
             */
            if ($change->tradingEventId !== null && TradingEventRepository::available()) {
                if ($this->tradingEvents->markProcessed($change->tradingEventId, $lead->id)) {
                    $this->tradingEvents->attachActivity($change->tradingEventId, $activity->id);
                }
            }
        });

        return $adjustment->refresh();
    }
}
