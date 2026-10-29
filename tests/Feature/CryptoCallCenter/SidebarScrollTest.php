<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Krayin renders the desktop menu inside `#admin-sidebar > .journal-scroll` with
 * `overflow-hidden`, so once the panel has more entries than fit a laptop screen
 * the last ones (deposits, for instance) are unreachable: the menu is clipped
 * rather than scrollable.
 *
 * The package fixes that with a CSS rule pushed into the `styles` stack of the
 * layout instead of a copy of the sidebar Blade, so the tests check that the rule
 * reaches the `<head>` exactly once and that the core sidebar stays untouched.
 */
it('pushes the sidebar scrolling rule into the head of the admin layout', function () {
    $html = test()->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertOk()
        ->getContent();

    $marker = strpos($html, 'data-crypto-call-center="sidebar-scroll"');
    $style = $marker === false ? '' : substr($html, $marker, strpos($html, '</style>', $marker) - $marker);

    expect(substr_count($html, 'data-crypto-call-center="sidebar-scroll"'))->toBe(1)
        ->and($marker)->toBeInt()
        ->and(strpos($html, '</head>'))->toBeInt()
        ->and($marker)->toBeLessThan(strpos($html, '</head>'))
        ->and($style)->toContain('@media (min-width: 1024px)')
        ->and($style)->toContain('.sidebar-not-collapsed #admin-sidebar > .journal-scroll')
        ->and($style)->toContain('overflow-y: auto')
        ->and($style)->toContain('overflow-x: hidden')
        ->and($style)->toContain('scrollbar-width: thin');

    /**
     * The collapsed rail keeps Krayin's own `overflow-visible`, otherwise its
     * icon flyouts would be trapped inside the 70px column.
     */
    expect($html)->not->toContain('.sidebar-collapsed #admin-sidebar');

    /** The fix is CSS only - the core desktop sidebar was not copied into the package. */
    expect(view('admin::components.layouts.sidebar.desktop.index')->getPath())
        ->toContain('Webkul'.DIRECTORY_SEPARATOR.'Admin')
        ->not->toContain('CryptoCallCenter');
});

it('keeps the sidebar scrolling rule off the screens without a sidebar', function () {
    test()->get(route('admin.session.create'))
        ->assertOk()
        ->assertDontSee('data-crypto-call-center="sidebar-scroll"', false);
});
