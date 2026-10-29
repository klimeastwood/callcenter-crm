<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Refusal of a staged deposit.
 *
 * A reason is required: "rejected" alone leaves the client without an answer,
 * and the trading cabinet prints the note next to the request.
 */
class RejectDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return bouncer()->hasPermission('deposits.review');
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
            'reason' => trans('crypto_call_center::app.deposits.review.reason'),
        ];
    }
}
