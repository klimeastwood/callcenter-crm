<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('crypto_call_center::app.deposits.show.title', ['client' => $deposit->user?->name ?? '—'])
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header Section -->
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between gap-4">
                <div class="text-xl font-bold dark:text-white">
                    @lang('crypto_call_center::app.deposits.show.title', ['client' => $deposit->user?->name ?? '—'])
                </div>

                <span class="{{ $deposit->status === 'approved' ? 'label-active' : 'label-inactive' }}">
                    {{ $deposit->statusLabel() }}
                </span>
            </div>

            <a
                class="text-sm text-brandColor transition-all hover:underline"
                href="{{ route('admin.crypto_call_center.deposits.index') }}"
            >
                @lang('crypto_call_center::app.deposits.show.back')
            </a>
        </div>

        @if ($status = session('deposit_success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                {{ $status }}
            </div>
        @endif

        @if ($refusal = $errors->first('deposit'))
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                {{ $refusal }}
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Request -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 text-base font-semibold dark:text-white">
                    @lang('crypto_call_center::app.deposits.show.request')
                </div>

                <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.client')</dt>
                        <dd class="text-right font-medium dark:text-white">{{ $deposit->user?->name ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.email')</dt>
                        <dd class="text-right">{{ $deposit->user?->email ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.amount')</dt>
                        <dd class="text-right text-base font-semibold dark:text-white">
                            ${{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::money($deposit->amount_usd) }}
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.created')</dt>
                        <dd class="text-right">{{ core()->formatDate($deposit->created_at, 'd.m.Y H:i') }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.request-id')</dt>
                        <dd class="text-right">{{ $deposit->id }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Card -->
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 text-base font-semibold dark:text-white">
                    @lang('crypto_call_center::app.deposits.show.card')
                </div>

                @if ($pan)
                    <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.holder')</dt>
                            <dd class="text-right dark:text-white">{{ $deposit->card->holder_name }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.brand')</dt>
                            <dd class="text-right">{{ $deposit->card->brandLabel() }}</dd>
                        </div>

                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.expiry')</dt>
                            <dd class="text-right">{{ $deposit->card->expiry }}</dd>
                        </div>

                        <div class="mt-2 flex flex-col gap-1">
                            <div class="text-gray-500 dark:text-gray-400">
                                @lang('crypto_call_center::app.deposits.show.number')
                            </div>

                            <div class="font-mono text-lg font-semibold tracking-wider dark:text-white">
                                {{ $pan }}
                            </div>
                        </div>

                        @if ($deposit->card_cvv)
                            <div class="mt-2 flex flex-col gap-1">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @lang('crypto_call_center::app.deposits.show.cvv')
                                </div>

                                <div class="font-mono text-lg font-semibold tracking-widest dark:text-white">
                                    {{ $deposit->card_cvv }}
                                </div>
                            </div>
                        @endif
                    </dl>
                @else
                    <!-- The row is sealed with a key this CRM does not hold. -->
                    <div class="text-sm dark:text-gray-300">
                        @lang($keyConfigured ? 'crypto_call_center::app.deposits.show.unreadable' : 'crypto_call_center::app.deposits.show.not-configured')
                    </div>
                @endif

                <div class="mt-4 border-t border-gray-300 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    @lang('crypto_call_center::app.deposits.show.warning')
                </div>
            </div>
        </div>

        <!-- Review -->
        <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 text-base font-semibold dark:text-white">
                @lang('crypto_call_center::app.deposits.show.review')
            </div>

            @if ($deposit->reviewed_at || $deposit->review_note)
                <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.reviewed-by')</dt>
                        <dd class="text-right dark:text-white">{{ $deposit->reviewer?->name ?? '—' }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.reviewed-at')</dt>
                        <dd class="text-right">
                            {{ $deposit->reviewed_at ? core()->formatDate($deposit->reviewed_at, 'd.m.Y H:i') : '—' }}
                        </dd>
                    </div>

                    @if ($deposit->review_note)
                        <div class="flex flex-col gap-1">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('crypto_call_center::app.deposits.show.review-note')</dt>
                            <dd class="whitespace-pre-line dark:text-white">{{ $deposit->review_note }}</dd>
                        </div>
                    @endif
                </dl>
            @else
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @lang('crypto_call_center::app.deposits.show.no-review')
                </div>
            @endif
        </div>

        @if ($deposit->isPending() && bouncer()->hasPermission('deposits.review'))
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Approve -->
                <form
                    method="POST"
                    action="{{ route('admin.crypto_call_center.deposits.approve', $deposit->id) }}"
                    class="flex flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                >
                    @csrf

                    <div class="text-base font-semibold dark:text-white">
                        @lang('crypto_call_center::app.deposits.review.approve-title')
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('crypto_call_center::app.deposits.review.approve-hint', [
                            'amount' => '$'.\Webkul\CryptoCallCenter\Support\TradingEventPresenter::money($deposit->amount_usd),
                        ])
                    </p>

                    <label for="deposit-note" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('crypto_call_center::app.deposits.review.note')
                    </label>

                    <input
                        id="deposit-note"
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
                        @lang('crypto_call_center::app.deposits.review.approve')
                    </button>
                </form>

                <!-- Reject -->
                <form
                    method="POST"
                    action="{{ route('admin.crypto_call_center.deposits.reject', $deposit->id) }}"
                    class="flex flex-col gap-3 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                >
                    @csrf

                    <div class="text-base font-semibold dark:text-white">
                        @lang('crypto_call_center::app.deposits.review.reject-title')
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @lang('crypto_call_center::app.deposits.review.reject-hint')
                    </p>

                    <label for="deposit-reason" class="text-xs font-medium text-gray-600 dark:text-gray-300">
                        @lang('crypto_call_center::app.deposits.review.reason')
                    </label>

                    <input
                        id="deposit-reason"
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
                        @lang('crypto_call_center::app.deposits.review.reject')
                    </button>
                </form>
            </div>
        @elseif ($deposit->isPending())
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                @lang('crypto_call_center::app.deposits.show.waiting')
            </div>
        @endif
    </div>
</x-admin::layouts>
