<?php

namespace Webkul\CryptoCallCenter\Services\Withdrawal;

use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Exceptions\WithdrawalReviewException;

/**
 * Records the payout decision the operator made in the CRM.
 *
 * A payout moves no money here: the client stages the request in the cabinet
 * and the back office pays it outside both applications. What the decision
 * changes is the request row the client reads, and that row belongs to the
 * trading application - so it is written over the bridge when the trading side
 * is reachable, and directly into the shared table when the configuration
 * declares the database as the transport.
 *
 * The implementation is idempotent per request: a retry of a lost response that
 * arrives after the first write returns `false` instead of rewriting the row,
 * so a second delivery cannot flip a decision or overwrite the operator's note.
 */
interface WithdrawalReviewGateway
{
    /**
     * Write the decision, or report that it was already there.
     *
     * @param  string  $status  `approved` or `rejected` on a pending request, `paid` on an approved one.
     * @param  string|null  $note  The note the client reads in the cabinet; ignored when completing a payout.
     * @param  int|null  $reviewerId  The CRM account that decided; both applications share the `users` table.
     * @return bool `true` when this call wrote the decision, `false` when the row already carried it.
     *
     * @throws WithdrawalReviewException When the request cannot be decided.
     * @throws TradingPlatformException When the trading side refuses or cannot be reached.
     */
    public function decide(
        int $requestId,
        string $status,
        ?string $note,
        ?int $reviewerId,
    ): bool;
}
