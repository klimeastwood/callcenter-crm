<?php

namespace Webkul\CryptoCallCenter\Services\Withdrawal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;

/**
 * Sends the payout decision to the trading application over its bridge.
 *
 * The HTTP call is the primary path: the trading application owns the request
 * row and the transition rules around it, so the CRM asks instead of writing
 * into a table it does not own. A refusal (422) is passed to the operator word
 * for word - "already approved" is exactly what it has to read. A configuration
 * error or a lost connection is only replaced by a direct write when
 * `crypto_call_center.trading.fallback` allows it. A completion is the
 * exception: `paid` debits the balance, which only the trading application may
 * do, so the direct transport refuses it and the payout stays approved until
 * the bridge answers.
 *
 * The endpoint is idempotent per request and status: a retry of a lost response
 * answers `applied = false`, so the operator sees an already decided request
 * instead of a second write.
 */
class HttpWithdrawalReviewGateway implements WithdrawalReviewGateway
{
    public function __construct(protected DatabaseWithdrawalReviewGateway $database) {}

    public function decide(
        int $requestId,
        string $status,
        ?string $note,
        ?int $reviewerId,
    ): bool {
        $baseUrl = rtrim((string) config('crypto_call_center.trading.base_url'), '/');

        $token = (string) config('crypto_call_center.trading.token');

        if ($baseUrl === '' || $token === '') {
            return $this->fallback(
                $requestId, $status, $note, $reviewerId,
                trans('crypto_call_center::app.withdrawals.errors.not-configured')
            );
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('crypto_call_center.trading.timeout', 5)))
                ->post('api/withdrawals/review', [
                    'request_id' => $requestId,
                    'status' => $status,
                    'note' => $note,
                    'reviewer_id' => $reviewerId,
                ]);
        } catch (ConnectionException $exception) {
            return $this->fallback(
                $requestId, $status, $note, $reviewerId,
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
                (string) ($response->json('message') ?: trans('crypto_call_center::app.withdrawals.errors.refused'))
            );
        }

        if ($response->serverError()) {
            return $this->fallback(
                $requestId, $status, $note, $reviewerId,
                (string) ($response->json('message') ?: 'HTTP '.$response->status())
            );
        }

        if (! $response->successful()) {
            throw new TradingPlatformException(
                trans('crypto_call_center::app.withdrawals.errors.rejected', ['status' => $response->status()])
            );
        }

        return (bool) $response->json('applied', true);
    }

    /**
     * Write an approval or a rejection into the shared table, or explain why it
     * cannot be done. A completion reaches the same guard in
     * `DatabaseWithdrawalReviewGateway` and is refused: only the bridge debits
     * the balance.
     */
    protected function fallback(
        int $requestId,
        string $status,
        ?string $note,
        ?int $reviewerId,
        string $cause,
    ): bool {
        if (! (bool) config('crypto_call_center.trading.fallback', false)) {
            report(new TradingPlatformException('Trading withdrawal bridge is unavailable: '.$cause));

            throw new TradingPlatformException(trans('crypto_call_center::app.withdrawals.errors.unreachable'));
        }

        return $this->database->decide($requestId, $status, $note, $reviewerId);
    }
}
