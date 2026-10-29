<?php

namespace Webkul\CryptoCallCenter\Services\Withdrawal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\CryptoCallCenter\Exceptions\WithdrawalReviewException;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;

/**
 * Writes the decision into the shared `withdrawal_requests` table.
 *
 * This is the transport of a single-database installation and the fallback of a
 * configured bridge. The row is locked for the duration of the write, so two
 * operators clicking at once cannot produce two decisions: the second one reads
 * the status the first one wrote and is refused, or - when it repeats the same
 * decision - told that nothing was applied.
 *
 * Approving and rejecting move no money and are written here. Completing a
 * payout is refused: `paid` debits the trader's balance, and only the trading
 * application owns that rule. Writing it here would close the request and leave
 * the money on the account, so that one transition requires the bridge.
 */
class DatabaseWithdrawalReviewGateway implements WithdrawalReviewGateway
{
    /**
     * The payout table owned by the trading application.
     */
    public const TABLE = 'withdrawal_requests';

    /**
     * Whether the trading side has been deployed against this database.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * @throws WithdrawalReviewException
     */
    public function decide(
        int $requestId,
        string $status,
        ?string $note,
        ?int $reviewerId,
    ): bool {
        if (! static::available()) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.unavailable')
            );
        }

        /**
         * A completed payout moves money, and the balance is owned by the
         * trading application: only `WithdrawalService::review()` may debit it.
         * This transport cannot reach that service, so a `paid` written here
         * would close the request and leave the amount on the account. The
         * approval and the rejection do not move money and still fall back.
         */
        if ($status === WithdrawalRequest::STATUS_PAID) {
            throw new WithdrawalReviewException(
                trans('crypto_call_center::app.withdrawals.errors.requires-bridge')
            );
        }

        return DB::transaction(function () use ($requestId, $status, $note, $reviewerId): bool {
            $request = DB::table(self::TABLE)
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new WithdrawalReviewException(
                    trans('crypto_call_center::app.withdrawals.errors.not-found')
                );
            }

            /**
             * A retry of a lost response is not a second decision: the row
             * already carries what this call would write, and rewriting it
             * would only move `reviewed_at` and, on a replay with a different
             * note, overwrite the reason the client was given.
             */
            if ($request->status === $status) {
                return false;
            }

            /**
             * The decision path here is `pending -> approved|rejected`;
             * `approved -> paid` was refused above because it moves money.
             * Anything else is a stale page or a second decision, and is
             * refused instead of overwriting the row.
             */
            $allowed = $request->status === WithdrawalRequest::STATUS_PENDING
                && in_array($status, [WithdrawalRequest::STATUS_APPROVED, WithdrawalRequest::STATUS_REJECTED], true);

            if (! $allowed) {
                throw new WithdrawalReviewException(
                    trans('crypto_call_center::app.withdrawals.errors.already-reviewed', [
                        'status' => $this->statusLabel((string) $request->status),
                    ])
                );
            }

            $note = $note === null ? null : trim($note);

            DB::table(self::TABLE)
                ->where('id', $requestId)
                ->update([
                    'status' => $status,
                    'is_approved' => $status === WithdrawalRequest::STATUS_APPROVED,
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                    'review_note' => $note === '' ? null : $note,
                    'updated_at' => now(),
                ]);

            return true;
        }, 3);
    }

    /**
     * The status in the panel language, with the raw value as a last resort.
     */
    protected function statusLabel(string $status): string
    {
        $key = 'crypto_call_center::app.withdrawals.status.'.$status;

        $label = trans($key);

        return $label === $key ? $status : $label;
    }
}
