<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Http\Requests\BalanceAdjustmentRequest;
use Webkul\CryptoCallCenter\Services\BalanceAdjustmentService;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;

/**
 * Applies a manual balance change and returns to the lead card.
 *
 * The form travels as a normal POST so the block works without JavaScript: the
 * redirect carries the success line or the refusal of the trading side, and the
 * browser lands back on the card it was sent from.
 */
class BalanceAdjustmentController extends Controller
{
    public function __construct(protected BalanceAdjustmentService $balanceAdjustments) {}

    public function store(BalanceAdjustmentRequest $request): RedirectResponse
    {
        $lead = $request->lead();

        try {
            $adjustment = $this->balanceAdjustments->adjust(
                $lead,
                auth()->guard('user')->user(),
                (float) $request->input('amount'),
                (string) $request->input('reason'),
            );
        } catch (TradingPlatformException $exception) {
            return back()
                ->withInput()
                ->withErrors(['balance_adjustment' => $exception->getMessage()]);
        }

        return back()->with('balance_adjustment_success', trans(
            'crypto_call_center::app.balance-adjustments.applied',
            [
                'amount' => TradingEventPresenter::signedMoney($adjustment->amount),
                'balance' => TradingEventPresenter::money($adjustment->balance_after),
            ]
        ));
    }
}
