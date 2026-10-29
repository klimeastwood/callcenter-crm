@php
    $operator = auth()->guard('user')->user();

    $canAdjust = \Webkul\CryptoCallCenter\Support\BalanceAdjustmentAccess::allows($operator, $lead);

    $history = $canAdjust
        ? app(\Webkul\CryptoCallCenter\Repositories\BalanceAdjustmentRepository::class)->latestForLead((int) $lead->id, 5)
        : collect();

    $balance = $lead->user_id
        ? app(\Webkul\CryptoCallCenter\Repositories\TradingAccountRepository::class)->balanceFor((int) $lead->user_id)
        : null;

    $applied = session('balance_adjustment_success');

    $refusal = $errors->first('balance_adjustment');
@endphp

@if ($canAdjust && \Webkul\CryptoCallCenter\Support\TradingBridge::ready())
    <!-- Trader Balance -->
    <div class="rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-300 px-4 py-2.5 dark:border-gray-800">
            <p class="text-sm font-semibold dark:text-white">
                @lang('crypto_call_center::app.balance-adjustments.title')
            </p>
        </div>

        <div class="flex flex-col gap-4 p-4">
            @if ($balance !== null)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="text-gray-500 dark:text-gray-300">
                        @lang('crypto_call_center::app.balance-adjustments.current-balance')
                    </span>

                    <span class="font-semibold {{ $balance < 0 ? 'text-red-600' : 'text-green-600' }}">
                        ${{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::money($balance) }}
                    </span>
                </div>
            @endif

            @if ($applied)
                <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                    {{ $applied }}
                </div>
            @endif

            @if ($refusal)
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                    {{ $refusal }}
                </div>
            @endif

            @if (! $lead->user_id)
                <p class="text-sm text-gray-500 dark:text-gray-300">
                    @lang('crypto_call_center::app.balance-adjustments.no-account')
                </p>
            @else
                <form
                    method="POST"
                    action="{{ route('admin.crypto_call_center.balance_adjustments.store', $lead->id) }}"
                    class="flex flex-col gap-3"
                >
                    @csrf

                    <div class="flex flex-col gap-1">
                        <label for="balance-adjustment-amount" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            @lang('crypto_call_center::app.balance-adjustments.form.amount')
                        </label>

                        <input
                            id="balance-adjustment-amount"
                            type="number"
                            name="amount"
                            step="0.01"
                            value="{{ old('amount') }}"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition-all focus:border-gray-400 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            required
                        />

                        @error('amount')
                            <p class="text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="balance-adjustment-reason" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            @lang('crypto_call_center::app.balance-adjustments.form.reason')
                        </label>

                        <input
                            id="balance-adjustment-reason"
                            type="text"
                            name="reason"
                            maxlength="255"
                            value="{{ old('reason') }}"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition-all focus:border-gray-400 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            required
                        />

                        @error('reason')
                            <p class="text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-300">
                        @lang('crypto_call_center::app.balance-adjustments.hint')
                    </p>

                    <button type="submit" class="primary-button">
                        @lang('crypto_call_center::app.balance-adjustments.form.submit')
                    </button>
                </form>
            @endif

            <div class="flex flex-col gap-2 border-t border-gray-200 pt-3 dark:border-gray-800">
                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-300">
                    @lang('crypto_call_center::app.balance-adjustments.history')
                </p>

                @forelse ($history as $adjustment)
                    @php
                        $amount = (float) $adjustment->amount;
                    @endphp

                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="flex flex-col">
                            <span class="font-medium {{ $amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::signedMoney($amount) }}
                            </span>

                            <span class="text-xs text-gray-500 dark:text-gray-300">
                                {{ $adjustment->reason }}
                            </span>
                        </div>

                        <div class="flex flex-col items-end text-xs text-gray-500 dark:text-gray-300">
                            <span>{{ optional($adjustment->admin)->name }}</span>

                            <span>{{ core()->formatDate($adjustment->created_at, 'd.m.Y H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 dark:text-gray-300">
                        @lang('crypto_call_center::app.balance-adjustments.empty-history')
                    </p>
                @endforelse
            </div>
        </div>
    </div>
@endif

