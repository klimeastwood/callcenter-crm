<?php

namespace Webkul\CryptoCallCenter\Services\Withdrawal;

use Webkul\CryptoCallCenter\Exceptions\WithdrawalReviewException;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;
use Webkul\CryptoCallCenter\Repositories\WithdrawalRequestRepository;
use Webkul\User\Models\User;

/**
 * Approves, rejects and completes a payout request.
 *
 * A payout moves no money in the CRM: approving it only closes the request in
 * the cabinet and the back office pays it outside both applications.
 * `complete()` records that the payment is done and asks the trading side for
 * it - which debits the amount from the trader's balance there, once. A payout
 * the balance can no longer cover is refused by that side and the refusal
 * reaches the operator word for word. The row is therefore written by the
 * gateway - over the bridge for every decision, or directly into the shared
 * table for an approval and a rejection - and the service reads it back, so the
 * screen shows what the trading side actually stored. A completion never takes
 * the direct route: `paid` debits the balance, which only the trading
 * application may do.
 *
 * No transaction is held across the gateway call on purpose. An HTTP call that
 * writes the same row would deadlock against a lock taken here: the trading
 * application opens its own connection, and the row this service would lock is
 * the very row the bridge has to update. The gateway locks the row itself and is
 * idempotent per request, so the second operator either repeats a decision that
 * is already stored (`applied = false`) or is refused with the current status.
 */
class WithdrawalReviewService
{
    public function __construct(
        protected WithdrawalReviewGateway $gateway,
        protected WithdrawalRequestRepository $withdrawals,
    ) {}

    /**
     * Approve the payout: the request is closed and the client sees the new
     * status. No money leaves an account on this call.
     *
     * @throws WithdrawalReviewException When the request was already decided or the trading side refuses.
     */
    public function approve(int $requestId, User $operator, ?string $note = null): WithdrawalRequest
    {
        return $this->decide($requestId, WithdrawalRequest::STATUS_APPROVED, $operator, $note);
    }

    /**
     * Refuse the payout. The reason is stored and shown to the client.
     *
     * @throws WithdrawalReviewException When the request was already decided.
     */
    public function reject(int $requestId, User $operator, string $reason): WithdrawalRequest
    {
        return $this->decide($requestId, WithdrawalRequest::STATUS_REJECTED, $operator, $reason);
    }

    /**
     * Confirm that the approved payout has actually been completed.
     *
     * The back office pays the request outside both applications; this call
     * closes it as `paid`, which is the status the cabinet shows as
     * «Выплачено», and the trading side takes the amount out of the trader's
     * balance in the same transaction. If the balance no longer covers the
     * payout the trading side refuses and the request stays approved, so the
     * operator can retry after topping the account up.
     *
     * The approval itself is not rewritten: the reviewer, the decision time and
     * the note stay those of the operator who opened the payout, because there
     * is no column that would hold a second decision.
     *
     * Repeating the confirmation on a request that is already completed is
     * answered without a write, so a double click or a resent stale page does
     * not fail. Only an approved request may be completed.
     *
     * @throws WithdrawalReviewException When the request is missing, was not approved or the trading side refuses.
     */
    public function complete(int $requestId): WithdrawalRequest
    {
        $request = $this->find($requestId);

        if ($request->isCompleted()) {
            return $request;
        }

        if (! $request->isApproved()) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.not-approved', [
                    'status' => $request->statusLabel(),
                ])
            );
        }

        $this->gateway->decide($requestId, WithdrawalRequest::STATUS_PAID, null, null);

        return $request->refresh();
    }

    /**
     * @throws WithdrawalReviewException
     */
    protected function decide(int $requestId, string $status, User $operator, ?string $note): WithdrawalRequest
    {
        $request = $this->pending($requestId);

        $this->gateway->decide(
            $requestId,
            $status,
            $this->note($note),
            $operator->id,
        );

        return $request->refresh();
    }

    /**
     * Refuse a request that is not waiting any more before the bridge is
     * touched, so a stale page answers immediately instead of opening a socket.
     *
     * @throws WithdrawalReviewException
     */
    protected function pending(int $requestId): WithdrawalRequest
    {
        $request = $this->find($requestId);

        if (! $request->isPending()) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.already-reviewed', [
                    'status' => $request->statusLabel(),
                ])
            );
        }

        return $request;
    }

    /**
     * The request from the shared table, or the explanation of why it cannot
     * be reached. Both decisions start here, so a deployment without the
     * trading tables answers the same way on every action.
     *
     * @throws WithdrawalReviewException
     */
    protected function find(int $requestId): WithdrawalRequest
    {
        if (! WithdrawalRequestRepository::available()) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.unavailable')
            );
        }

        $request = $this->withdrawals->find($requestId);

        if ($request === null) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.not-found')
            );
        }

        return $request;
    }

    /**
     * A note that is only whitespace is no note at all, and `null` keeps the
     * column empty instead of storing an empty string.
     */
    protected function note(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        return $note === '' ? null : $note;
    }
}
