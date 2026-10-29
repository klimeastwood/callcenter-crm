<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Refusal of a staged payout.
 *
 * A reason is required: "rejected" alone leaves the client without an answer,
 * and the trading cabinet prints the note next to the request.
 */
class RejectWithdrawalRequest extends FormRequest
{
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
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => trans('crypto_call_center::app.withdrawals.review.reason'),
        ];
    }
}
