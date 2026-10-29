@php
    /**
     * Only a finished payout is a success. A pending request is the row the desk
     * scans for, and a rejected one is closed just as quietly, so the two share
     * the neutral badge - the wording, not the colour, says which is which.
     */
    $isPaid = in_array($row->status, ['approved', 'paid'], true);

    /**
     * A status this build does not know is shown as the trading side wrote it,
     * not as a raw translation key.
     */
    $key = 'crypto_call_center::app.withdrawals.status.'.$row->status;
    $label = trans($key);
@endphp

<span class="{{ $isPaid ? 'label-active' : 'label-inactive' }}">
    {{ $label === $key ? $row->status : $label }}
</span>
