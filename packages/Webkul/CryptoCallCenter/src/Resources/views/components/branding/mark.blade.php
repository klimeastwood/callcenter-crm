{{--
    KLIMIATA brand mark: a "C" monogram in the CRM brand colour.

    The colour is inherited (`currentColor` → `text-brandColor`), so callers
    only pick a size:

        @include('crypto_call_center::components.branding.mark', ['class' => 'h-6 w-6'])
--}}
<svg
    class="text-brandColor {{ $class ?? 'h-9 w-9' }}"
    viewBox="0 0 40 40"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
>
    <rect width="40" height="40" rx="11" fill="currentColor" />

    <path
        d="M24.6 13.5a8 8 0 1 0 0 13"
        stroke="#fff"
        stroke-width="3.4"
        stroke-linecap="round"
    />
</svg>
