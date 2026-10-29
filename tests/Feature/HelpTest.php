<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('shows the help page to an authenticated admin', function () {
    /** The help screen is translated into Russian, so the English copy is pinned by locale. */
    useAdminLocale('en');

    $admin = getDefaultAdmin();

    /**
     * The page title is a Blade slot, so `@lang()` prints it unescaped and
     * `assertSee()` has to be told that. The escaped copy used to come from the
     * English sidebar label, which the `ru` locale now translates.
     */
    test()->actingAs($admin)
        ->get(route('admin.help.index'))
        ->assertOk()
        ->assertSee('Help & Resources', false)
        ->assertSee('Cloud Hosting')
        ->assertSee('Extensions')
        ->assertSee('krayincrm.com/cloud-hosting')
        ->assertSee('Still need a hand?')
        ->assertSee('Community Forums')
        ->assertSee('Video Tutorials');
});
