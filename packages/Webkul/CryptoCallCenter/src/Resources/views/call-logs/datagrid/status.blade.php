@php
    /**
     * Only a completed conversation is a success. A missed or failed call is
     * exactly the row a supervisor scans for, so both share the alert badge.
     */
    $isCompleted = $row->status === 'completed';
@endphp

<span class="{{ $isCompleted ? 'label-active' : 'label-inactive' }}">
    @lang('crypto_call_center::app.call-logs.status.'.$row->status)
</span>
