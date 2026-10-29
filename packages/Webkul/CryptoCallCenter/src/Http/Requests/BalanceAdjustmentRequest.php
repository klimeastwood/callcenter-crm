<?php

namespace Webkul\CryptoCallCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webkul\CryptoCallCenter\Support\BalanceAdjustmentAccess;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\LeadProxy;

/**
 * A manual balance change submitted from the lead card.
 *
 * The amount is signed on purpose: one form covers a bonus and a write-off, and
 * the history keeps both. The ceiling is the same one the trading side applies,
 * so the operator reads the refusal before the request leaves the CRM.
 */
class BalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BalanceAdjustmentAccess::allows(
            auth()->guard('user')->user(),
            $this->lead(),
        );
    }

    /**
     * The lead the form belongs to.
     *
     * The admin routes bind `{lead}` to the model before the request reaches the
     * controller, so the parameter is usually already resolved. The fallback
     * keeps the request usable if the binding is ever dropped from the route.
     */
    public function lead(): ?Lead
    {
        $lead = $this->route('lead');

        if ($lead instanceof Lead) {
            return $lead;
        }

        $leadModel = LeadProxy::modelClass();

        return $leadModel::query()->find((int) $lead);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = (float) config('crypto_call_center.trading.max_amount', 1000000);

        $max = $max > 0 ? $max : 1000000;

        return [
            'amount' => ['required', 'numeric', 'between:-'.$max.','.$max],
            'reason' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    /**
     * A zero amount passes `numeric` but changes nothing, so it is refused here
     * instead of being turned into an event the history cannot explain.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ((float) $this->input('amount') == 0.0) {
                    $validator->errors()->add(
                        'amount',
                        trans('crypto_call_center::app.balance-adjustments.errors.zero-amount')
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => trans('crypto_call_center::app.balance-adjustments.form.amount'),
            'reason' => trans('crypto_call_center::app.balance-adjustments.form.reason'),
        ];
    }
}
