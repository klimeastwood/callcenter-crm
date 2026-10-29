<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Completion of an approved payout.
 *
 * The operator only confirms that the money has left the account, so the request
 * carries no payload: the status is the decision, and the approval that opened
 * the payout keeps its reviewer, time and note.
 */
class CompleteWithdrawalRequest extends FormRequest
{
    /**
     * Completing a payout is a payout-desk action, so it is guarded by the same
     * permission that reveals the card and decides the request.
     */
    public function authorize(): bool
    {
        return bouncer()->hasPermission('withdrawals.card');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
