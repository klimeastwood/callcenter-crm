<?php

namespace Webkul\CryptoCallCenter\Support;

use Webkul\CryptoCallCenter\Models\TradingEvent;

/**
 * Words and numbers for a trading event.
 *
 * The lead timeline and the "Trading events" block on the lead card both read
 * this class, so a deposit is worded and formatted identically wherever it
 * appears. Messages are built with `trans()`, which keeps the Russian and
 * English files in sync with the panel language chosen in the configuration.
 */
class TradingEventPresenter
{
    /**
     * Whether the CRM knows how to describe the event type.
     */
    public static function supports(?string $type): bool
    {
        return in_array($type, TradingEvent::TYPES, true);
    }

    /**
     * One-line summary, used as the activity title and as the block heading.
     */
    public static function title(TradingEvent $event, ?string $locale = null): string
    {
        $payload = static::payload($event);

        return match ($event->event_type) {
            TradingEvent::TYPE_DEPOSIT => trans('crypto_call_center::app.trading-events.activity.deposit', [
                'amount' => static::money($payload['amount'] ?? 0),
            ], $locale),

            TradingEvent::TYPE_POSITION_OPENED => trans(
                'crypto_call_center::app.trading-events.activity.'.(
                    ($payload['side'] ?? null) === 'sell' ? 'short' : 'long'
                ),
                [
                    'symbol' => static::symbol($payload),
                    'amount' => static::money(static::notional($payload)),
                ],
                $locale
            ),

            TradingEvent::TYPE_POSITION_CLOSED => static::closedTitle($payload, $locale),

            TradingEvent::TYPE_BALANCE_ADJUSTMENT => static::adjustmentTitle($payload, $locale),

            TradingEvent::TYPE_WITHDRAWAL_REQUESTED => trans('crypto_call_center::app.trading-events.activity.withdrawal', [
                'amount' => static::money($payload['amount'] ?? 0),
            ], $locale),

            TradingEvent::TYPE_DEPOSIT_REQUESTED => trans('crypto_call_center::app.trading-events.activity.deposit-requested', [
                'amount' => static::money($payload['amount'] ?? 0),
            ], $locale),

            default => trans('crypto_call_center::app.trading-events.activity.unknown', [
                'type' => (string) $event->event_type,
            ], $locale),
        };
    }

    /**
     * The details line: balance and price context for the summary above.
     *
     * Null when the payload carries none of them, so that the activity keeps a
     * clean title instead of a line of zeroes.
     */
    public static function comment(TradingEvent $event, ?string $locale = null): ?string
    {
        $payload = static::payload($event);

        $parts = match ($event->event_type) {
            TradingEvent::TYPE_DEPOSIT => static::depositComment($payload, $locale),

            TradingEvent::TYPE_POSITION_OPENED => array_filter([
                isset($payload['amount'], $payload['symbol'])
                    ? trans('crypto_call_center::app.trading-events.activity.volume', [
                        'amount' => static::quantity($payload['amount']),
                        'symbol' => static::symbol($payload),
                    ], $locale)
                    : null,

                isset($payload['entry_price'])
                    ? trans('crypto_call_center::app.trading-events.activity.entry', [
                        'price' => static::money($payload['entry_price']),
                    ], $locale)
                    : null,

                static::balance($payload, $locale),
            ]),

            TradingEvent::TYPE_POSITION_CLOSED => array_filter([
                isset($payload['entry_price'], $payload['close_price'])
                    ? trans('crypto_call_center::app.trading-events.activity.prices', [
                        'entry' => static::money($payload['entry_price']),
                        'close' => static::money($payload['close_price']),
                    ], $locale)
                    : null,

                static::balance($payload, $locale),
            ]),

            TradingEvent::TYPE_BALANCE_ADJUSTMENT => array_filter([
                isset($payload['reason'])
                    ? trans('crypto_call_center::app.trading-events.activity.adjustment-reason', [
                        'reason' => (string) $payload['reason'],
                    ], $locale)
                    : null,

                static::balance($payload, $locale),
            ]),

            TradingEvent::TYPE_WITHDRAWAL_REQUESTED => array_filter([
                static::withdrawalRequisites($payload, $locale),

                static::balance($payload, $locale),
            ]),

            TradingEvent::TYPE_DEPOSIT_REQUESTED => array_filter([
                static::depositRequisites($payload, $locale),

                static::balance($payload, $locale),
            ]),

            default => [],
        };

        $parts = array_values($parts);

        return $parts === [] ? null : implode('; ', $parts);
    }

    /**
     * Short type label for the lead card block.
     */
    public static function badge(TradingEvent $event, ?string $locale = null): ?string
    {
        $key = match ($event->event_type) {
            TradingEvent::TYPE_DEPOSIT => 'deposit',
            TradingEvent::TYPE_POSITION_OPENED => 'opened',
            TradingEvent::TYPE_POSITION_CLOSED => 'closed',
            TradingEvent::TYPE_BALANCE_ADJUSTMENT => 'adjustment',
            TradingEvent::TYPE_WITHDRAWAL_REQUESTED => 'withdrawal',
            TradingEvent::TYPE_DEPOSIT_REQUESTED => 'deposit-requested',
            default => null,
        };

        return $key === null
            ? null
            : trans('crypto_call_center::app.trading-events.badge.'.$key, [], $locale);
    }

    /**
     * Badge class of the Krayin style guide, coloured by type.
     */
    public static function badgeClass(TradingEvent $event): string
    {
        /**
         * Green is reserved for money coming in or a position still making it.
         * A payout request is money on its way out and a closed position is the
         * end of a trade, so both stay neutral.
         */
        return in_array($event->event_type, [
            TradingEvent::TYPE_POSITION_CLOSED,
            TradingEvent::TYPE_WITHDRAWAL_REQUESTED,
        ], true)
            ? 'label-inactive'
            : 'label-active';
    }

    /**
     * A deposit request stays green too: it is money coming in, even though the
     * balance has not moved yet - the badge says "Deposit requested" to make the
     * wait visible.
     *
     * Format a money amount the way the panel reads it: no cents when the
     * amount is whole, a space as the thousands separator, a dot for cents.
     */
    public static function money(float|int|string|null $value): string
    {
        $amount = round((float) $value, 2);

        $decimals = $amount == floor($amount) ? 0 : 2;

        return number_format($amount, $decimals, '.', ' ');
    }

    /**
     * Format a quantity of coins, which needs its small decimals kept.
     */
    protected static function quantity(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 8, '.', ' '), '0'), '.') ?: '0';
    }

    /**
     * The manual balance change, worded by its direction.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function adjustmentTitle(array $payload, ?string $locale): string
    {
        $amount = (float) ($payload['amount'] ?? 0);

        return trans('crypto_call_center::app.trading-events.activity.'.(
            $amount < 0 ? 'adjustment-write-off' : 'adjustment-credit'
        ), [
            'amount' => static::money(abs($amount)),
        ], $locale);
    }

    /**
     * "$1 000" for a bonus, "-$500" for a write-off.
     */
    public static function signedMoney(float|int|string|null $value): string
    {
        return ((float) $value < 0 ? '-$' : '+$').static::money(abs((float) $value));
    }

    /**
     * The closed position summary, chosen by the sign of the result.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function closedTitle(array $payload, ?string $locale): string
    {
        $result = (float) ($payload['pnl'] ?? 0);

        $key = match (true) {
            $result > 0 => 'closed-profit',
            $result < 0 => 'closed-loss',
            default => 'closed-flat',
        };

        $replace = ['symbol' => static::symbol($payload)];

        if ($key !== 'closed-flat') {
            $replace['amount'] = static::money(abs($result));
        }

        return trans('crypto_call_center::app.trading-events.activity.'.$key, $replace, $locale);
    }

    /**
     * Where a payout is going, as the trading side recorded it.
     *
     * The payload of `withdrawal.requested` carries the last four digits of a
     * card and the wallet the client typed, never a number, so the timeline can
     * say "to a Visa ending 4242" without holding anything secret.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function withdrawalRequisites(array $payload, ?string $locale): ?string
    {
        $last4 = $payload['card_last4'] ?? null;

        if ($last4) {
            return trans('crypto_call_center::app.trading-events.activity.withdrawal-card', [
                'number' => '**** '.$last4,
                'brand' => ($payload['card_brand'] ?? null)
                    ?: trans('crypto_call_center::app.withdrawals.brands.unknown', [], $locale),
            ], $locale);
        }

        $wallet = (string) ($payload['wallet_address'] ?? '');

        if ($wallet === '') {
            return null;
        }

        return trans('crypto_call_center::app.trading-events.activity.withdrawal-wallet', [
            'wallet' => strlen($wallet) > 14
                ? substr($wallet, 0, 6).'...'.substr($wallet, -4)
                : $wallet,
        ], $locale);
    }

    /**
     * The card a deposit is expected from, masked.
     *
     * The trading log writes `card_last4` and `card_brand`; a row without them
     * simply has no requisites line instead of a broken one.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function depositRequisites(array $payload, ?string $locale): ?string
    {
        $last4 = $payload['card_last4'] ?? null;

        if (! $last4) {
            return null;
        }

        return trans('crypto_call_center::app.trading-events.activity.deposit-card', [
            'number' => '**** '.$last4,
            'brand' => ($payload['card_brand'] ?? null)
                ?: trans('crypto_call_center::app.deposits.brands.unknown', [], $locale),
        ], $locale);
    }

    /**
     * Balance before and after a deposit.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected static function depositComment(array $payload, ?string $locale): array
    {
        if (! isset($payload['balance_before'], $payload['balance_after'])) {
            return [];
        }

        return [
            trans('crypto_call_center::app.trading-events.activity.balance', [
                'before' => static::money($payload['balance_before']),
                'after' => static::money($payload['balance_after']),
            ], $locale),
        ];
    }

    /**
     * "Balance: $1 500" when the payload carries the balance after the event.
     *
     * The trading log writes `balance_after` for a trade and a plain `balance`
     * for a payout request, so both spellings are read here instead of leaving
     * the payout line without its context.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function balance(array $payload, ?string $locale): ?string
    {
        $balance = $payload['balance_after'] ?? $payload['balance'] ?? null;

        if ($balance === null) {
            return null;
        }

        return trans('crypto_call_center::app.trading-events.activity.balance-after', [
            'balance' => static::money($balance),
        ], $locale);
    }

    /**
     * Trade size in dollars, falling back to price times quantity for a log
     * that does not store the notional value.
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function notional(array $payload): float
    {
        if (isset($payload['notional'])) {
            return (float) $payload['notional'];
        }

        return (float) ($payload['amount'] ?? 0) * (float) ($payload['entry_price'] ?? 0);
    }

    /**
     * The pair that was traded, e.g. "BTCUSDT".
     *
     * @param  array<string, mixed>  $payload
     */
    protected static function symbol(array $payload): string
    {
        return (string) ($payload['symbol'] ?? '—');
    }

    /**
     * The decoded payload, tolerating an empty or malformed column.
     *
     * @return array<string, mixed>
     */
    protected static function payload(TradingEvent $event): array
    {
        $payload = $event->payload;

        return is_array($payload) ? $payload : [];
    }
}
