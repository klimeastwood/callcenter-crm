<?php

namespace Webkul\CryptoCallCenter\Services\Deposit;

use Illuminate\Support\Facades\DB;
use Webkul\CryptoCallCenter\Exceptions\DepositReviewException;
use Webkul\CryptoCallCenter\Models\DepositRequest;
use Webkul\CryptoCallCenter\Services\Balance\BalanceChange;
use Webkul\User\Models\User;

/**
 * Approves or rejects a deposit request.
 *
 * Approval is the only path that moves money: the trading side credits the
 * balance and writes a `deposit` event under the request reference, and only
 * then is the row marked as reviewed. Both steps live in one transaction, so the
 * CRM never shows an approved request that was not credited. A retry of a lost
 * response is safe because the gateway is idempotent per reference.
 *
 * Rejection writes nothing but the decision and the reason: no money moves, and
 * the client reads the reason in the cabinet.
 *
 * The row lock is held across the gateway call on purpose. The two applications
 * share a database but not a transaction, so atomicity is impossible either way;
 * what the lock buys is that only one operator ever reaches the trading side for
 * a given request. The call is bounded by `crypto_call_center.trading.timeout`
 * and the endpoint is idempotent per reference, so the worst case of a lost
 * response is a retry that returns `applied = false` and closes the row.
 */
class DepositReviewService
{
    public function __construct(protected DepositBalanceGateway $gateway) {}

    /**
     * Confirm the transfer and credit the account.
     *
     * @return array{0: DepositRequest, 1: BalanceChange}
     *
     * @throws DepositReviewException When the request was already decided or the trading side refuses.
     */
    public function approve(int $depositId, User $operator, ?string $note = null): array
    {
        return DB::transaction(function () use ($depositId, $operator, $note): array {
            $request = $this->lockPending($depositId);

            $change = $this->gateway->credit(
                (int) $request->user_id,
                (float) $request->amount_usd,
                $this->reference($request),
                $operator->name,
            );

            $request->forceFill([
                'status' => DepositRequest::STATUS_APPROVED,
                'is_approved' => true,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
                'review_note' => $this->note($note),
            ])->save();

            return [$request, $change];
        });
    }

    /**
     * Refuse the deposit. The reason is stored and shown to the client.
     *
     * @throws DepositReviewException When the request was already decided.
     */
    public function reject(int $depositId, User $operator, string $reason): DepositRequest
    {
        return DB::transaction(function () use ($depositId, $operator, $reason): DepositRequest {
            $request = $this->lockPending($depositId);

            $request->forceFill([
                'status' => DepositRequest::STATUS_REJECTED,
                'is_approved' => false,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
                'review_note' => $this->note($reason),
            ])->save();

            return $request;
        });
    }

    /**
     * The idempotency key the trading side stores with the deposit event.
     */
    public function reference(DepositRequest $request): string
    {
        return 'deposit:'.$request->id;
    }

    /**
     * Row lock the request and refuse a second decision, so two operators
     * clicking at once cannot credit the same deposit twice.
     *
     * @throws DepositReviewException
     */
    protected function lockPending(int $depositId): DepositRequest
    {
        $request = DepositRequest::query()
            ->whereKey($depositId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $request->isPending()) {
            throw new DepositReviewException(trans(
                'crypto_call_center::app.deposits.errors.already-reviewed',
                ['status' => $request->statusLabel()]
            ));
        }

        return $request;
    }

    /**
     * Trim the free text, treat an empty field as "no note".
     */
    protected function note(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
