<?php

namespace Webkul\CryptoCallCenter\Services\Deposit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;

/**
 * Applies an approved deposit straight into the tables the trading application
 * owns.
 *
 * The CRM and the trading application share one database, so this is a valid
 * replacement when the bridge is not reachable - but it duplicates the rules of
 * `TradingService::creditDeposit()` on purpose: the account row is ensured, the
 * balance is locked, the request is credited once per reference and a `deposit`
 * event is written for the CRM feed. Keep the two in sync or run the bridge
 * instead, which is the default.
 */
class DatabaseDepositBalanceGateway implements DepositBalanceGateway
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

    public function credit(int $userId, float $amount, string $reference, ?string $operatorName = null): BalanceChange
    {
        if (! static::available()) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.deposits.errors.unavailable')
            );
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.deposits.errors.zero-amount')
            );
        }

        if (trim($reference) === '') {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.deposits.errors.reference-required')
            );
        }

        $max = (float) config('crypto_call_center.trading.max_amount', 1000000);

        if ($max > 0 && $amount > $max) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.deposits.errors.amount-too-big', [
                    'max' => number_format($max, 0, '.', ' '),
                ])
            );
        }

        return DB::transaction(function () use ($userId, $amount, $reference, $operatorName): BalanceChange {
            /**
             * A retry whose first response was lost must not credit twice, so
             * the request reference is the key of the event.
             */
            $existing = $this->findDepositEvent($reference);

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

            DB::table(self::ACCOUNTS_TABLE)
                ->where('user_id', $userId)
                ->update([
                    'balance_usd' => $balanceAfter,
                    'updated_at' => now(),
                ]);

            $tradingEventId = DB::table(self::EVENTS_TABLE)->insertGetId([
                'user_id' => $userId,
                'event_type' => TradingEvent::TYPE_DEPOSIT,
                'payload' => json_encode([
                    'reference' => $reference,
                    'amount' => $amount,
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
     * The deposit event written for an earlier delivery of the same request.
     */
    protected function findDepositEvent(string $reference): ?TradingEvent
    {
        return TradingEvent::query()
            ->where('event_type', TradingEvent::TYPE_DEPOSIT)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.reference')) = ?", [$reference])
            ->first();
    }
}
