<?php

namespace Webkul\CryptoCallCenter\Services\Balance;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;

/**
 * Sends the change to the trading application over its bridge endpoint.
 *
 * The HTTP call is the primary path: the trading side owns the balance and the
 * rules around it (no negative balance, the event log), so the CRM asks instead
 * of writing into a table it does not own. A refusal (422) is passed to the
 * operator word for word; a configuration error or a lost connection is only
 * replaced by a direct write when `crypto_call_center.trading.fallback` allows
 * it.
 */
class HttpTradingBalanceGateway implements TradingBalanceGateway
{
    public function __construct(protected DatabaseTradingBalanceGateway $database) {}

    public function adjust(int $userId, float $amount, string $reason, int $adjustmentId, ?string $operatorName = null): BalanceChange
    {
        $baseUrl = rtrim((string) config('crypto_call_center.trading.base_url'), '/');

        $token = (string) config('crypto_call_center.trading.token');

        if ($baseUrl === '' || $token === '') {
            return $this->fallback(
                $userId, $amount, $reason, $adjustmentId, $operatorName,
                trans('crypto_call_center::app.balance-adjustments.errors.not-configured')
            );
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('crypto_call_center.trading.timeout', 5)))
                ->post('api/balance/adjust', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'reason' => $reason,
                    'adjustment_id' => $adjustmentId,
                    'operator_name' => $operatorName,
                ]);
        } catch (ConnectionException $exception) {
            return $this->fallback(
                $userId, $amount, $reason, $adjustmentId, $operatorName,
                $exception->getMessage()
            );
        }

        /**
         * A validated request the trading side still refuses (the balance would
         * go below zero, the amount is above its own ceiling) is the operator's
         * problem to fix, not a transport failure: falling back would repeat the
         * same refusal with a second implementation.
         */
        if ($response->status() === 422) {
            throw new TradingPlatformException(
                (string) ($response->json('message') ?: trans('crypto_call_center::app.balance-adjustments.errors.refused'))
            );
        }

        if ($response->serverError()) {
            return $this->fallback(
                $userId, $amount, $reason, $adjustmentId, $operatorName,
                (string) ($response->json('message') ?: 'HTTP '.$response->status())
            );
        }

        if (! $response->successful()) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.balance-adjustments.errors.rejected', ['status' => $response->status()])
            );
        }

        $tradingEventId = $response->json('event_id');

        return new BalanceChange(
            (float) $response->json('balance_before', 0),
            (float) $response->json('balance_after', 0),
            is_numeric($tradingEventId) ? (int) $tradingEventId : null,
            (bool) $response->json('applied', true),
        );
    }

    /**
     * Write the change into the shared tables, or explain why it cannot be done.
     */
    protected function fallback(
        int $userId,
        float $amount,
        string $reason,
        int $adjustmentId,
        ?string $operatorName,
        string $cause,
    ): BalanceChange {
        if (! (bool) config('crypto_call_center.trading.fallback', false)) {
            report(new TradingPlatformException('Trading bridge is unavailable: '.$cause));

            throw new TradingPlatformException(trans('crypto_call_center::app.balance-adjustments.errors.unreachable'));
        }

        return $this->database->adjust($userId, $amount, $reason, $adjustmentId, $operatorName);
    }
}
