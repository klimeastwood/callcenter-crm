<?php

namespace Webkul\CryptoCallCenter\Services\Balance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Models\TradingEvent;

/**
 * Applies the change straight into the tables the trading application owns.
 *
 * The CRM and the trading application share one database, so this is a valid
 * replacement when the bridge is not reachable - but it duplicates the rules of
 * `TradingService::adjustBalance()` on purpose: no zero amount, no negative
 * balance, a capped amount, one event per CRM adjustment. Keep the two in sync
 * or run the bridge instead, which is the default.
 */
class DatabaseTradingBalanceGateway implements TradingBalanceGateway
{
    /**
     * The account table owned by the trading application.
     */
    public const ACCOUNTS_TABLE = 'trading_accounts';

    /**
     * The event log shared with the CRM.
     */
    public const EVENTS_TABLE = 'trading_events';

    /**
     * Whether the trading side has been deployed against this database.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::ACCOUNTS_TABLE)
            && Schema::hasTable(self::EVENTS_TABLE);
    }

    public function adjust(int $userId, float $amount, string $reason, int $adjustmentId, ?string $operatorName = null): BalanceChange
    {
        if (! static::available()) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.unavailable')
            );
        }

        $amount = round($amount, 2);

        $reason = trim($reason);

        if ($amount == 0.0) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.zero-amount')
            );
        }

        if ($reason === '') {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.reason-required')
            );
        }

        $max = (float) config('crypto_call_center.trading.max_amount', 1000000);

        if ($max > 0 && abs($amount) > $max) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.amount-too-big', [
                    'max' => number_format($max, 0, '.', ' '),
                ])
            );
        }

        return DB::transaction(function () use ($userId, $amount, $reason, $adjustmentId, $operatorName): BalanceChange {
            /**
             * A retry whose first response was lost must not credit twice, so
             * the CRM row id travels as the reference of the event.
             */
            $existing = $this->findAdjustmentEvent($adjustmentId);

            if ($existing) {
                $payload = $existing->payload ?? [];

                return new BalanceChange(
                    (float) ($payload['balance_before'] ?? 0),
                    (float) ($payload['balance_after'] ?? 0),
                    (int) $existing->id,
                    false,
                );
            }

            DB::table(self::ACCOUNTS_TABLE)->insertOrIgnore([
                'user_id' => $userId,
                'balance_usd' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $account = DB::table(self::ACCOUNTS_TABLE)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            $balanceBefore = (float) $account->balance_usd;

            $balanceAfter = round($balanceBefore + $amount, 8);

            if ($balanceAfter < 0) {
                throw new TradingPlatformException(
                    trans('crypto_call_center::app.balance-adjustments.errors.insufficient', [
                        'balance' => number_format($balanceBefore, 2, '.', ' '),
                    ])
                );
            }

            DB::table(self::ACCOUNTS_TABLE)
                ->where('user_id', $userId)
                ->update([
                    'balance_usd' => $balanceAfter,
                    'updated_at' => now(),
                ]);

            $tradingEventId = DB::table(self::EVENTS_TABLE)->insertGetId([
                'user_id' => $userId,
                'event_type' => TradingEvent::TYPE_BALANCE_ADJUSTMENT,
                'payload' => json_encode([
                    'reference' => (string) $adjustmentId,
                    'amount' => $amount,
                    'reason' => $reason,
                    'operator_name' => $operatorName,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return new BalanceChange($balanceBefore, $balanceAfter, (int) $tradingEventId, true);
        }, 3);
    }

    /**
     * The event written for an earlier delivery of the same CRM adjustment.
     */
    protected function findAdjustmentEvent(int $adjustmentId): ?TradingEvent
    {
        return TradingEvent::query()
            ->where('event_type', TradingEvent::TYPE_BALANCE_ADJUSTMENT)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.reference')) = ?", [(string) $adjustmentId])
            ->first();
    }
}
