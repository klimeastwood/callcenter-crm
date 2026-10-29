@php
    /**
     * What the payout desk reads while the money leaves: the wallet the client
     * named or the masked card. The full number is opened on the request page
     * only, so it never lands in an exported or screenshotted grid.
     *
     * The branch is taken from the requisites themselves and never from
     * `$row->method`: the method column is rendered first and replaces the raw
     * value with its translated label, so a later closure no longer sees
     * `crypto` there. A wallet is public data, so it is printed in full and can
     * be copied; a card number is not, so only its last four digits are shown.
     */
    $wallet = trim((string) ($row->wallet_address ?? ''));

    /**
     * A brand this build does not know is labelled as a plain card instead of
     * leaking a raw translation key into the grid.
     */
    $brandKey = 'crypto_call_center::app.withdrawals.brands.'.($row->card_brand ?: 'unknown');
    $brand = trans($brandKey);
@endphp

@if ($wallet !== '')
    <span class="font-mono text-xs break-all">{{ $wallet }}</span>
@elseif ($row->card_last4)
    <span class="font-mono text-xs">•••• •••• •••• {{ $row->card_last4 }}</span>

    <span class="text-xs text-gray-500 dark:text-gray-400">
        ({{ $brand === $brandKey ? trans('crypto_call_center::app.withdrawals.brands.unknown') : $brand }})
    </span>
@else
    @lang('crypto_call_center::app.withdrawals.index.datagrid.empty')
@endif
