<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation of a staged deposit.
 *
 * The note is optional: most approvals need no explanation, but a line here is
 * shown to the client in the cabinet next to the amount.
 */
class ApproveDepositRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => trans('crypto_call_center::app.deposits.review.note'),
        ];
    }
}
