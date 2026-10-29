@php
    /**
     * Only a confirmed deposit is a success. A pending request is the row the
     * desk scans for and a rejected one is closed just as quietly, so the two
     * share the neutral badge - the wording, not the colour, says which is which.
     */
    $isCredited = $row->status === 'approved';

    /**
     * A status this build does not know is shown as the trading side wrote it,
     * not as a raw translation key.
     */
    $key = 'crypto_call_center::app.deposits.status.'.$row->status;
    $label = trans($key);
@endphp

<span class="{{ $isCredited ? 'label-active' : 'label-inactive' }}">
    {{ $label === $key ? $row->status : $label }}
</span>
