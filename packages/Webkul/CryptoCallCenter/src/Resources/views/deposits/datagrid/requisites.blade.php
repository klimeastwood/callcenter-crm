@php
    /**
     * What the deposit desk verifies: the masked card the money came from.
     *
     * The full number is opened on the request page only, behind
     * `deposits.review`, so it never lands in an exported or screenshotted grid.
     * A brand this build does not know is labelled as a plain card instead of
     * leaking a raw translation key into the grid.
     */
    $brandKey = 'crypto_call_center::app.deposits.brands.'.($row->card_brand ?: 'unknown');
    $brand = trans($brandKey);
@endphp

@if ($row->card_last4)
    <span class="font-mono text-xs">•••• •••• •••• {{ $row->card_last4 }}</span>

    <span class="text-xs text-gray-500 dark:text-gray-400">
        ({{ $brand === $brandKey ? trans('crypto_call_center::app.deposits.brands.unknown') : $brand }})
    </span>
@else
    @lang('crypto_call_center::app.deposits.index.datagrid.empty')
@endif
