<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The admin `Locale` middleware reads `general.general.locale_settings.locale`
 * from the system config on every request, so the shared `useAdminLocale()`
 * helper (tests/Pest.php) just writes that row.
 */
it('offers Russian among the available locales', function () {
    expect(collect(core()->locales())->pluck('value'))->toContain('ru');
});

it('renders the sign-in screen in Russian', function () {
    useAdminLocale('ru');

    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee('Вход')
        ->assertSee('Электронная почта')
        ->assertSee('Пароль')
        ->assertSee('Забыли пароль?')
        ->assertSee('Войти')
        ->assertDontSee('Forget Password ?');
});

it('renders the dashboard and the sidebar in Russian', function () {
    useAdminLocale('ru');

    test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee('Дашборд')
        ->assertSee('Контакты')
        ->assertSee('Мой аккаунт')
        ->assertSee('Выйти');
});

it('keeps the English screens in English', function () {
    useAdminLocale('en');

    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertSee('Sign In')
        ->assertSee('Forget Password ?')
        ->assertDontSee('Забыли пароль?');
});

it('falls back to English for the branded line the package keeps in English', function () {
    /** The branding line is deliberately not translated, so the footer stays English. */
    expect(trans('admin::app.components.layouts.powered-by.description', [], 'ru'))
        ->toContain('Powered by');
});

it('translates every admin line except the branding footer', function () {
    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $lines = [];

        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $lines += $flatten($value, $full);

                continue;
            }

            $lines[$full] = $value;
        }

        return $lines;
    };

    $english = $flatten(
        require base_path('packages/Webkul/Admin/src/Resources/lang/en/app.php')
    );

    $russian = $flatten(
        require base_path('packages/Webkul/CryptoCallCenter/src/Resources/lang/overrides/ru/admin/app.php')
    );

    expect(array_keys(array_diff_key($english, $russian)))
        ->toBe(['components.layouts.powered-by.description'])
        ->and(array_keys(array_diff_key($russian, $english)))
        ->toBe([]);
});

it('keeps the Russian lines free of untranslated leftovers', function () {
    expect(trans('admin::app.leads.index.title', [], 'ru'))->toBe('Лиды')
        ->and(trans('admin::app.configuration.index.general.settings.footer.title', [], 'ru'))
        ->toBe('Настройки раздела «Powered by»')
        ->and(trans('admin::app.mail.index.compose', [], 'ru'))->toBe('Написать');
});

it('renders the leads, mail and activities screens in Russian', function () {
    useAdminLocale('ru');

    test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.leads.index'))
        ->assertOk()
        ->assertSee('Лиды')
        ->assertSee('Создать лид')
        ->assertDontSee('admin::app.leads');

    test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.mail.index', ['route' => 'inbox']))
        ->assertOk()
        ->assertDontSee('admin::app.mail');

    test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.activities.index'))
        ->assertOk()
        ->assertDontSee('admin::app.activities');
});

it('renders the configuration screen in Russian', function () {
    useAdminLocale('ru');

    $response = test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'general']))
        ->assertOk()
        ->assertSee('Языковые настройки')
        ->assertSee('Логотип админ-панели');

    /**
     * Config metadata is also embedded into `field-data` attributes, so only the
     * text nodes are checked for keys the translator failed to resolve.
     */
    preg_match_all('/>admin::app\.[a-zA-Z0-9._-]+/', $response->getContent(), $matches);

    expect($matches[0])->toBe([]);
});
