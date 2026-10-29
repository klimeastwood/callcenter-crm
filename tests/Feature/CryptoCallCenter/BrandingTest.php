<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * The admin panel is rebranded from the package: the Krayin wordmark becomes the
 * CRYPTOISK logo and both "powered by" footers point at KLIMIATA instead of Krayin.
 *
 * The footer of the authenticated layout is a system config default, the footer
 * of the session screens is a translation line and the logo is a Blade override,
 * so all three mechanisms are exercised here.
 */
it('rebrands the header and the footer of the admin panel', function () {
    test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.branding.name'))
        ->assertSee(trans('crypto_call_center::app.branding.powered-by'))
        ->assertDontSee('krayincrm.com')
        ->assertDontSee('an open-source project by');

    /** The header and the drawer come from the package override, not from the Admin package. */
    expect(view('admin::components.layouts.header.index')->getPath())
        ->toContain('CryptoCallCenter')
        ->and(view('admin::components.layouts.sidebar.mobile.index')->getPath())
        ->toContain('CryptoCallCenter');
});

it('rebrands the footer of the login screen without losing the other strings', function () {
    test()
        ->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.branding.powered-by'))
        ->assertDontSee('krayincrm.com')
        ->assertDontSee('an open-source project by')

        /** Overriding one line must not hide the rest of the `admin::app` group. */
        ->assertSee(trans('admin::app.users.login.title'))
        ->assertDontSee('admin::app.users.login');
});

it('prints the CRYPTOISK logo instead of the Krayin wordmark on the sign-in screen', function () {
    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.branding.name'))
        ->assertSee('viewBox="0 0 40 40"', false)
        ->assertSee('letter-spacing: 0.14em', false)
        ->assertDontSee('images/logo.svg');

    /** The screen comes from the package override, not from the Admin package. */
    expect(view('admin::sessions.login')->getPath())->toContain('CryptoCallCenter');
});

it('prints the CRYPTOISK logo on the password screens of the sign-in flow', function () {
    $screens = [
        route('admin.forgot_password.create'),
        route('admin.reset_password.create', ['token' => 'test-token']),
    ];

    foreach ($screens as $url) {
        test()->get($url)
            ->assertOk()
            ->assertSee(trans('crypto_call_center::app.branding.name'))
            ->assertSee('viewBox="0 0 40 40"', false)
            ->assertSee(trans('crypto_call_center::app.branding.powered-by'))
            ->assertDontSee('images/logo.svg');
    }

    expect(view('admin::sessions.forgot-password')->getPath())
        ->toContain('CryptoCallCenter')
        ->and(view('admin::sessions.reset-password')->getPath())
        ->toContain('CryptoCallCenter');
});

it('keeps a logo uploaded in the configuration on the sign-in screen', function () {
    DB::table('core_config')->updateOrInsert(
        ['code' => 'general.general.admin_logo.logo_image'],
        ['value' => 'admin/custom-logo.png']
    );

    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee('custom-logo.png')
        ->assertDontSee('viewBox="0 0 40 40"', false);
});

it('keeps the branding footer in English on the Russian screens', function () {
    useAdminLocale('ru');

    /** Branding names the vendor, so it is the one line the translator leaves alone. */
    $line = trans('crypto_call_center::app.branding.powered-by', [], 'en');

    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee('Вход')
        ->assertSee($line)
        ->assertDontSee('Работает на');

    test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertOk()
        ->assertSee($line)
        ->assertDontSee('Работает на');
});
