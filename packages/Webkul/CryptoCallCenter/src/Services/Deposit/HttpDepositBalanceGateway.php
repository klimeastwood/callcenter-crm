<?php

namespace Webkul\CryptoCallCenter\Services\Deposit;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;

/**
 * Sends the approved deposit to the trading application over its bridge.
 *
 * The HTTP call is the primary path: the trading side owns the balance and the
 * rules around it (the account row, the `deposit` event), so the CRM asks
 * instead of writing into a table it does not own. A refusal (422) is passed to
 * the operator word for word; a configuration error or a lost connection is only
 * replaced by a direct write when `crypto_call_center.trading.fallback` allows
 * it.
 */
class HttpDepositBalanceGateway implements DepositBalanceGateway
{
    public function __construct(protected DatabaseDepositBalanceGateway $database) {}

    public function credit(int $userId, float $amount, string $reference, ?string $operatorName = null): BalanceChange
    {
        $baseUrl = rtrim((string) config('crypto_call_center.trading.base_url'), '/');

        $token = (string) config('crypto_call_center.trading.token');

        if ($baseUrl === '' || $token === '') {
            return $this->fallback(
                $userId, $amount, $reference, $operatorName,
                trans('crypto_call_center::app.deposits.errors.not-configured')
            );
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('crypto_call_center.trading.timeout', 5)))
                ->post('api/deposits/credit', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'reference' => $reference,
                    'operator_name' => $operatorName,
                ]);
        } catch (ConnectionException $exception) {
            return $this->fallback(
                $userId, $amount, $reference, $operatorName,
                $exception->getMessage()
            );
        }

        /**
         * A validated request the trading side still refuses is the operator's
         * problem to fix, not a transport failure: falling back would repeat the
         * same refusal with a second implementation.
         */
        if ($response->status() === 422) {
            throw new TradingPlatformException(
                (string) ($response->json('message') ?: trans('crypto_call_center::app.deposits.errors.refused'))
            );
        }

        if ($response->serverError()) {
            return $this->fallback(
                $userId, $amount, $reference, $operatorName,
                (string) ($response->json('message') ?: 'HTTP '.$response->status())
            );
        }

        if (! $response->successful()) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.deposits.errors.rejected', ['status' => $response->status()])
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
     * Write the deposit into the shared tables, or explain why it cannot be done.
     */
    protected function fallback(
        int $userId,
        float $amount,
        string $reference,
        ?string $operatorName,
        string $cause,
    ): BalanceChange {
        if (! (bool) config('crypto_call_center.trading.fallback', false)) {
            report(new TradingPlatformException('Trading deposit bridge is unavailable: '.$cause));

            throw new TradingPlatformException(trans('crypto_call_center::app.deposits.errors.unreachable'));
        }

        return $this->database->credit($userId, $amount, $reference, $operatorName);
    }
}
