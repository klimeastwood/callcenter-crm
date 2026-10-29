<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Core\Menu;
use Webkul\CryptoCallCenter\Contracts\CryptoWallet as CryptoWalletContract;
use Webkul\CryptoCallCenter\Models\CryptoWallet;
use Webkul\CryptoCallCenter\Repositories\CryptoWalletRepository;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * A lead the wallets can belong to.
 *
 * The pipeline is resolved instead of assuming id 1, because the parallel
 * test databases are migrated but not seeded.
 */
function makeLeadForCryptoWallet(): Lead
{
    $pipeline = securityTestPipeline();

    return Lead::create([
        'title' => 'Crypto Wallet Lead '.bin2hex(random_bytes(4)),
        'user_id' => getDefaultAdmin()->id,
        'lead_value' => 1000,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);
}

/**
 * A user limited to a custom set of role permissions.
 */
function makeCryptoCallCenterUser(array $permissions): User
{
    $role = Role::create([
        'name' => 'role-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::create([
        'name' => 'user-'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

it('stores a crypto wallet for a lead through the repository', function () {
    $lead = makeLeadForCryptoWallet();

    $wallet = app(CryptoWalletRepository::class)->create([
        'lead_id' => $lead->id,
        'wallet_address' => '0x1234567890abcdef1234567890abcdef12345678',
        'network' => 'ethereum',
    ]);

    expect($wallet)->toBeInstanceOf(CryptoWallet::class)
        ->and($wallet->lead->id)->toBe($lead->id)
        ->and(app(CryptoWalletRepository::class)->findByLead($lead->id))->toHaveCount(1);
});

it('resolves the model through its contract', function () {
    $lead = makeLeadForCryptoWallet();

    $wallet = app(CryptoWalletContract::class)->create([
        'lead_id' => $lead->id,
        'wallet_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
        'network' => 'bitcoin',
    ]);

    /**
     * The count is scoped to the lead instead of the whole table: a development
     * database may already hold demo wallets, and this test must stay green there.
     */
    expect($wallet)->toBeInstanceOf(CryptoWallet::class)
        ->and(
            CryptoWallet::query()
                ->where('lead_id', $lead->id)
                ->where('network', 'bitcoin')
                ->count()
        )->toBe(1);
});

it('lists the wallets of a lead on the admin screen', function () {
    $lead = makeLeadForCryptoWallet();

    app(CryptoWalletRepository::class)->create([
        'lead_id' => $lead->id,
        'wallet_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
        'network' => 'tron',
    ]);

    /**
     * The title comes from the package translation file, so it follows the panel locale instead of
     * being asserted as a fixed English string.
     */
    test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertOk()
        ->assertSee(trans('crypto_call_center::app.wallets.index.title'));

    /** The menu item is registered through the package config, not by editing the Admin package. */
    $menuKeys = app(Menu::class)->getItems('admin')->map(fn ($item) => $item->getKey());

    expect($menuKeys)->toContain('crypto_call_center');

    $response = test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.crypto_call_center.wallets.index'))
        ->assertOk();

    /**
     * The wallet written above is the newest row, so it is the first record even
     * when a development database already holds demo wallets. The total is
     * therefore only checked to be non-empty.
     */
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(1)
        ->and($response->json('records.0.wallet_address'))->toBe('TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE')
        ->and($response->json('records.0.network'))->toBe('tron');
});

it('guards the screen with the package ACL entry', function () {
    $allowed = makeCryptoCallCenterUser(['crypto_call_center']);
    $denied = makeCryptoCallCenterUser(['contacts']);

    test()
        ->actingAs($allowed, 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertOk();

    test()
        ->actingAs($denied, 'user')
        ->get(route('admin.crypto_call_center.wallets.index'))
        ->assertUnauthorized();
});
