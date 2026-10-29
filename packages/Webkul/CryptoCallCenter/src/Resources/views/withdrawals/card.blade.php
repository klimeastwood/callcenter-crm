<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('crypto_call_center::app.withdrawals.card.title', ['client' => $withdrawal->user?->name ?? '—'])
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header Section -->
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between gap-4">
                <div class="text-xl font-bold dark:text-white">
                    @lang('crypto_call_center::app.withdrawals.card.title', ['client' => $withdrawal->user?->name ?? '—'])
                </div>

                <span class="{{ in_array($withdrawal->status, ['approved', 'paid'], true) ? 'label-active' : 'label-inactive' }}">
                    {{ $withdrawal->statusLabel() }}
                </span>
            </div>

            <a
                class="text-sm text-brandColor transition-all hover:underline"
                href="{{ route('admin.crypto_call_center.withdrawals.index') }}"
            >
                @lang('crypto_call_center::app.withdrawals.card.back')
            </a>
        </div>

        @if ($status = session('withdrawal_success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                {{ $status }}
            </div>
        @endif

        @if ($refusal = $errors->first('withdrawal'))
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                {{ $refusal }}
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Request -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 text-base font-semibold dark:text-white">
                    @lang('crypto_call_center::app.withdrawals.card.request')
                </div>

                <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.client')</dt>
                        <dd class="text-right font-medium dark:text-white">{{ $withdrawal->user?->name ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.email')</dt>
                        <dd class="text-right">{{ $withdrawal->user?->email ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.amount')</dt>
                        <dd class="text-right text-base font-semibold dark:text-white">
                            ${{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::money($withdrawal->amount_usd) }}
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.method')</dt>
                        <dd class="text-right">{{ $withdrawal->methodLabel() }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.created')</dt>
                        <dd class="text-right">{{ core()->formatDate($withdrawal->created_at, 'd.m.Y H:i') }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.request-id')</dt>
                        <dd class="text-right">{{ $withdrawal->id }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Requisites -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 text-base font-semibold dark:text-white">
                    @lang('crypto_call_center::app.withdrawals.card.requisites')
                </div>

                @if ($withdrawal->method === 'crypto')
                    <div class="flex flex-col gap-2 text-sm dark:text-gray-300">
                        <div class="text-gray-500 dark:text-gray-400">
                            @lang('crypto_call_center::app.withdrawals.card.wallet')
                        </div>

                        <div class="break-all font-mono text-sm dark:text-white">
                            {{ $withdrawal->wallet_address ?: '—' }}
                        </div>
                    </div>
                @elseif ($pan)
                    <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.holder')</dt>
                            <dd class="text-right dark:text-white">{{ $withdrawal->card->holder_name }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.brand')</dt>
                            <dd class="text-right">{{ $withdrawal->card->brandLabel() }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.expiry')</dt>
                            <dd class="text-right">{{ $withdrawal->card->expiry }}</dd>
                        </div>

                        <div class="mt-2 flex flex-col gap-1">
                            <div class="text-gray-500 dark:text-gray-400">
                                @lang('crypto_call_center::app.withdrawals.card.number')
                            </div>

                            <div class="font-mono text-lg font-semibold tracking-wider dark:text-white">
                                {{ $pan }}
                            </div>
                        </div>

                        @if ($withdrawal->card_cvv)
                            <div class="mt-2 flex flex-col gap-1">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @lang('crypto_call_center::app.withdrawals.card.cvv')
                                </div>

                                <div class="font-mono text-lg font-semibold tracking-widest dark:text-white">
                                    {{ $withdrawal->card_cvv }}
                                </div>
                            </div>
                        @endif
                    </dl>
                @else
                    <!-- The row is sealed with a key this CRM does not hold. -->
                    <div class="text-sm dark:text-gray-300">
                        @lang($keyConfigured ? 'crypto_call_center::app.withdrawals.card.unreadable' : 'crypto_call_center::app.withdrawals.card.not-configured')
                    </div>
                @endif

                <div class="mt-4 border-t border-gray-300 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    @lang('crypto_call_center::app.withdrawals.card.warning')
                </div>
            </div>
        </div>

        <!-- Review -->
        <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 text-base font-semibold dark:text-white">
                @lang('crypto_call_center::app.withdrawals.card.review')
            </div>

            @if ($withdrawal->reviewed_at || $withdrawal->review_note)
                <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.reviewed-by')</dt>
                        <dd class="text-right dark:text-white">{{ $withdrawal->reviewer?->name ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.reviewed-at')</dt>
                        <dd class="text-right">
                            {{ $withdrawal->reviewed_at ? core()->formatDate($withdrawal->reviewed_at, 'd.m.Y H:i') : '—' }}
                        </dd>
                    </div>

                    @if ($withdrawal->review_note)
                        <div class="flex flex-col gap-1">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.withdrawals.card.review-note')</dt>
                            <dd class="whitespace-pre-line dark:text-white">{{ $withdrawal->review_note }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @lang('crypto_call_center::app.withdrawals.card.no-review')
                </div>
            @endif
        </div>

        @if ($withdrawal->isPending() && bouncer()->hasPermission('withdrawals.card'))
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Approve -->
                <form
                    method="POST"
                    action="{{ route('admin.crypto_call_center.withdrawals.approve', $withdrawal->id) }}"
                    class="flex flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                >
                    @csrf

                    <div class="text-base font-semibold dark:text-white">
                        @lang('crypto_call_center::app.withdrawals.review.approve-title')
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('crypto_call_center::app.withdrawals.review.approve-hint', [
                            'amount' => '$'.\Webkul\CryptoCallCenter\Support\TradingEventPresenter::money($withdrawal->amount_usd),
                        ])
                    </p>

                    <label for="withdrawal-note" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('crypto_call_center::app.withdrawals.review.note')
                    </label>

                    <input
                        id="withdrawal-note"
                        type="text"
                        name="note"
                        maxlength="255"
                        value="{{ old('note') }}"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 transition-all focus:border-gray-400 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    />

                    @error('note')
                        <p class="text-xs italic text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="primary-button">
                        @lang('crypto_call_center::app.withdrawals.review.approve')
                    </button>
                </form>

                <!-- Reject -->
                <form
                    method="POST"
                    action="{{ route('admin.crypto_call_center.withdrawals.reject', $withdrawal->id) }}"
                    class="flex flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                >
                    @csrf

                    <div class="text-base font-semibold dark:text-white">
                        @lang('crypto_call_center::app.withdrawals.review.reject-title')
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('crypto_call_center::app.withdrawals.review.reject-hint')
                    </p>

                    <label for="withdrawal-reason" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('crypto_call_center::app.withdrawals.review.reason')
                    </label>

                    <input
                        id="withdrawal-reason"
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

                    <button type="submit" class="secondary-button">
                        @lang('crypto_call_center::app.withdrawals.review.reject')
                    </button>
                </form>
            </div>
        @elseif ($withdrawal->isApproved() && bouncer()->hasPermission('withdrawals.card') && \Webkul\CryptoCallCenter\Support\TradingBridge::canClosePayout())
            <!-- Complete -->
            <form
                method="POST"
                action="{{ route('admin.crypto_call_center.withdrawals.complete', $withdrawal->id) }}"
                class="flex flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
            >
                @csrf

                <div class="text-base font-semibold dark:text-white">
                    @lang('crypto_call_center::app.withdrawals.review.complete-title')
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @lang('crypto_call_center::app.withdrawals.review.complete-hint')
                </p>

                <button type="submit" class="primary-button">
                    @lang('crypto_call_center::app.withdrawals.review.complete')
                </button>
            </form>
        @elseif ($withdrawal->isApproved() && bouncer()->hasPermission('withdrawals.card'))
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                @lang('crypto_call_center::app.withdrawals.errors.requires-bridge')
            </div>
        @elseif ($withdrawal->isCompleted())
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                @lang('crypto_call_center::app.withdrawals.card.completed')
            </div>
        @elseif ($withdrawal->isPending())
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                @lang('crypto_call_center::app.withdrawals.card.waiting')
            </div>
        @endif
    </div>
</x-admin::layouts>
