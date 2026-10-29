<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\CryptoCallCenter\DataGrids\DepositDataGrid;
use Webkul\CryptoCallCenter\Exceptions\DepositReviewException;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Http\Requests\ApproveDepositRequest;
use Webkul\CryptoCallCenter\Http\Requests\RejectDepositRequest;
use Webkul\CryptoCallCenter\Repositories\DepositRequestRepository;
use Webkul\CryptoCallCenter\Services\Deposit\DepositReviewService;
use Webkul\CryptoCallCenter\Support\CardCipher;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;

class DepositController extends Controller
{
    /**
     * @param  DepositRequestRepository  $deposits  The deposit queue of the trading side.
     */
    public function __construct(
        protected DepositRequestRepository $deposits,
        protected DepositReviewService $review,
    ) {}

    /**
     * Display the deposits the clients staged in the trading cabinet.
     *
     * The tables belong to the trading application, so an installation that has
     * not deployed them gets an explanation instead of a broken grid.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax() && DepositRequestRepository::available()) {
            return datagrid(DepositDataGrid::class)->process();
        }

        return view('crypto_call_center::deposits.index', [
            'available' => DepositRequestRepository::available(),
        ]);
    }

    /**
     * Show one request: the card the money came from, the decision history and
     * the approve and reject forms.
     *
     * The number is opened here and nowhere else: it is never stored by the CRM,
     * never logged and never put into the grid. A deployment without the shared
     * key gets a sentence that says so, not a half-decrypted string.
     */
    public function show(int $deposit): View
    {
        $request = $this->deposits->findWithCard($deposit);

        abort_if($request === null, 404);

        return view('crypto_call_center::deposits.show', [
            'deposit' => $request,
            'pan' => $request->card?->formattedPan(),
            'keyConfigured' => CardCipher::configured(),
        ]);
    }

    /**
     * Confirm the transfer and credit the trader balance.
     *
     * The credit is performed by the trading side under the request reference,
     * so a retry cannot pay the same deposit twice. A refusal of the trading
     * side leaves the request pending and is shown to the operator word for
     * word.
     */
    public function approve(ApproveDepositRequest $request, int $deposit): RedirectResponse
    {
        try {
            [$approved, $change] = $this->review->approve(
                $deposit,
                auth()->guard('user')->user(),
                $request->input('note'),
            );
        } catch (DepositReviewException|TradingPlatformException $exception) {
            return redirect()
                ->route('admin.crypto_call_center.deposits.show', $deposit)
                ->withErrors(['deposit' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.crypto_call_center.deposits.show', $approved->id)
            ->with('deposit_success', trans('crypto_call_center::app.deposits.review.approved', [
                'amount' => TradingEventPresenter::money($approved->amount_usd),
                'balance' => TradingEventPresenter::money($change->balanceAfter),
            ]));
    }

    /**
     * Refuse the deposit. No money moves; the reason is stored for the client.
     */
    public function reject(RejectDepositRequest $request, int $deposit): RedirectResponse
    {
        try {
            $rejected = $this->review->reject(
                $deposit,
                auth()->guard('user')->user(),
                (string) $request->input('reason'),
            );
        } catch (DepositReviewException $exception) {
            return redirect()
                ->route('admin.crypto_call_center.deposits.show', $deposit)
                ->withErrors(['deposit' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.crypto_call_center.deposits.show', $rejected->id)
            ->with('deposit_success', trans('crypto_call_center::app.deposits.review.rejected'));
    }
}
