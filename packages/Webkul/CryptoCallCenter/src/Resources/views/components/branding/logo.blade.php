{{--
    CRYPTOISK wordmark: brand mark + product name.

    Included by the overridden admin header, the mobile drawer and the
    unauthenticated screens. The name is a translation key, so renaming the
    product does not need another Blade change; the mark and the wordmark are
    sized by the caller:

        @include('crypto_call_center::components.branding.logo', [
            'markClass' => 'h-8 w-8',
            'textClass' => 'text-xl',
        ])
--}}
<span class="flex items-center gap-2">
    @include('crypto_call_center::components.branding.mark', ['class' => $markClass ?? 'h-9 w-9'])

    <span
        class="{{ $textClass ?? 'text-lg' }} font-bold text-brandColor"
        style="letter-spacing: 0.14em;"
    >
        @lang('crypto_call_center::app.branding.name')
    </span>
</span>
