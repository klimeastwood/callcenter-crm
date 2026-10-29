<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\CryptoCallCenter\DataGrids\WithdrawalDataGrid;
use Webkul\CryptoCallCenter\Exceptions\TradingPlatformException;
use Webkul\CryptoCallCenter\Exceptions\WithdrawalReviewException;
use Webkul\CryptoCallCenter\Http\Requests\ApproveWithdrawalRequest;
use Webkul\CryptoCallCenter\Http\Requests\CompleteWithdrawalRequest;
use Webkul\CryptoCallCenter\Http\Requests\RejectWithdrawalRequest;
use Webkul\CryptoCallCenter\Repositories\WithdrawalRequestRepository;
use Webkul\CryptoCallCenter\Services\Withdrawal\WithdrawalReviewService;
use Webkul\CryptoCallCenter\Support\CardCipher;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;

class WithdrawalController extends Controller
{
    /**
     * @param  WithdrawalRequestRepository  $withdrawals  The payout queue of the trading side.
     * @param  WithdrawalReviewService  $review  The approve and reject actions of the payout desk.
     */
    public function __construct(
        protected WithdrawalRequestRepository $withdrawals,
        protected WithdrawalReviewService $review,
    ) {}

    /**
     * Display the payout requests the clients staged in the trading cabinet.
     *
     * The tables belong to the trading application, so an installation that has
     * not deployed them gets an explanation instead of a broken grid.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax() && WithdrawalRequestRepository::available()) {
            return datagrid(WithdrawalDataGrid::class)->process();
        }

        return view('crypto_call_center::withdrawals.index', [
            'available' => WithdrawalRequestRepository::available(),
        ]);
    }

    /**
     * Show one request, with the card number the payout desk has to pay to.
     *
     * The number is opened here and nowhere else: it is never stored, never
     * logged and never put into the grid. A deployment without the shared key
     * gets a sentence that says so, not a half-decrypted string.
     */
    public function card(int $withdrawal): View
    {
        $request = $this->withdrawals->findWithCard($withdrawal);

        abort_if($request === null, 404);

        return view('crypto_call_center::withdrawals.card', [
            'withdrawal' => $request,
            'pan' => $request->card?->formattedPan(),
            'keyConfigured' => CardCipher::configured(),
        ]);
    }

    /**
     * Approve the payout and close the request in the cabinet.
     *
     * No money moves here: the back office still pays the request, and the CRM
     * only records the decision the client reads. The write goes through the
     * bridge, so a refusal of the trading side leaves the request pending and is
     * shown to the operator word for word.
     */
    public function approve(ApproveWithdrawalRequest $request, int $withdrawal): RedirectResponse
    {
        try {
            $approved = $this->review->approve(
                $withdrawal,
                auth()->guard('user')->user(),
                $request->input('note'),
            );
        } catch (WithdrawalReviewException|TradingPlatformException $exception) {
            return redirect()
                ->route('admin.crypto_call_center.withdrawals.card', $withdrawal)
                ->withErrors(['withdrawal' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.crypto_call_center.withdrawals.card', $approved->id)
            ->with('withdrawal_success', trans('crypto_call_center::app.withdrawals.review.approved', [
                'amount' => TradingEventPresenter::money($approved->amount_usd),
            ]));
    }

    /**
     * Confirm that the approved payout is completed.
     *
     * The back office pays the request outside the CRM, so this action asks the
     * trading side to close it as `paid`, which is the status the cabinet shows
     * as «Выплачено». The trading side debits the amount from the balance in the
     * same transaction; the CRM itself never writes `balance_usd`.
     */
    public function complete(CompleteWithdrawalRequest $request, int $withdrawal): RedirectResponse
    {
        try {
            $completed = $this->review->complete($withdrawal);
        } catch (WithdrawalReviewException|TradingPlatformException $exception) {
            return redirect()
                ->route('admin.crypto_call_center.withdrawals.card', $withdrawal)
                ->withErrors(['withdrawal' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.crypto_call_center.withdrawals.card', $completed->id)
            ->with('withdrawal_success', trans('crypto_call_center::app.withdrawals.review.completed', [
                'amount' => TradingEventPresenter::money($completed->amount_usd),
            ]));
    }

    /**
     * Refuse the payout. No money moves; the reason is stored for the client.
     */
    public function reject(RejectWithdrawalRequest $request, int $withdrawal): RedirectResponse
    {
        try {
            $rejected = $this->review->reject(
                $withdrawal,
                auth()->guard('user')->user(),
                (string) $request->input('reason'),
            );
        } catch (WithdrawalReviewException|TradingPlatformException $exception) {
            return redirect()
                ->route('admin.crypto_call_center.withdrawals.card', $withdrawal)
                ->withErrors(['withdrawal' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.crypto_call_center.withdrawals.card', $rejected->id)
            ->with('withdrawal_success', trans('crypto_call_center::app.withdrawals.review.rejected'));
    }
}
