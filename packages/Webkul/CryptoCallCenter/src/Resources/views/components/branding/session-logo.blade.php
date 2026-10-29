{{--
    Logo of the unauthenticated screens: sign-in, forgot-password and
    reset-password.

    The three core templates print the Krayin wordmark (`images/logo.svg`)
    above the form; their overrides include this partial instead, so the
    logo of the whole sign-in flow lives in one place.

    A logo uploaded in Configuration → General → Settings still wins, exactly
    like in the header and the mobile drawer: the rebrand never hides a
    deliberate admin choice.
--}}
@if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
    <img
        class="h-10 w-[110px]"
        src="{{ Storage::url($logo) }}"
        alt="{{ config('app.name') }}"
    />
@else
    @include('crypto_call_center::components.branding.logo', [
        'markClass' => 'h-12 w-12',
        'textClass' => 'text-2xl',
    ])
@endif
