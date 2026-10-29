<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approval of a staged payout.
 *
 * Approving closes the request in the cabinet - the payout itself is paid by
 * the back office - so the note is optional but is shown to the client next to
 * the request.
 */
class ApproveWithdrawalRequest extends FormRequest
{
    /**
     * Deciding a payout is a payout-desk action, so it is the same permission
     * that opens the full number: nobody reads the card without being able to
     * close the request it belongs to.
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
        return [
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => trans('crypto_call_center::app.withdrawals.review.note'),
        ];
    }
}
