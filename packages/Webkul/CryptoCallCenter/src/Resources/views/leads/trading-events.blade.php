@php
    $tradingEvents = collect();

    if ($lead->user_id && \Webkul\CryptoCallCenter\Repositories\TradingEventRepository::available()) {
        $tradingEvents = app(\Webkul\CryptoCallCenter\Repositories\TradingEventRepository::class)
            ->latestForUser((int) $lead->user_id, 5);
    }
@endphp

@if ($tradingEvents->isNotEmpty())
    <!-- Trading Events -->
    <div class="rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-300 px-4 py-2.5 dark:border-gray-800">
            <p class="text-sm font-semibold dark:text-white">
                @lang('crypto_call_center::app.trading-events.title')
            </p>
        </div>

        <div class="flex flex-col gap-3 p-4">
            @foreach ($tradingEvents as $tradingEvent)
                @php
                    $badge = \Webkul\CryptoCallCenter\Support\TradingEventPresenter::badge($tradingEvent);

                    $comment = \Webkul\CryptoCallCenter\Support\TradingEventPresenter::comment($tradingEvent);
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($badge)
                                <span class="{{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::badgeClass($tradingEvent) }}">
                                    {{ $badge }}
                                </span>
                            @endif

                            <span class="font-medium dark:text-white">
                                {{ \Webkul\CryptoCallCenter\Support\TradingEventPresenter::title($tradingEvent) }}
                            </span>
                        </div>

                        @if ($comment)
                            <p class="text-xs text-gray-500 dark:text-gray-300">
                                {{ $comment }}
                            </p>
                        @endif
                    </div>

                    <span class="text-xs text-gray-500 dark:text-gray-300">
                        {{ core()->formatDate($tradingEvent->created_at, 'd.m.Y H:i') }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
