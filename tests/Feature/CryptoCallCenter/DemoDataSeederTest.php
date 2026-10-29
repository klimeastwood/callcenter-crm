<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Webkul\Contact\Models\Person;
use Webkul\CryptoCallCenter\Database\Seeders\DemoDataSeeder;
use Webkul\CryptoCallCenter\Models\CallLog;
use Webkul\CryptoCallCenter\Models\CryptoWallet;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

uses(DatabaseTransactions::class);

/**
 * The demo seeder expects the six stages the installer creates.
 *
 * The parallel test databases are migrated but not seeded, so the helper adds
 * the stages to the pipeline `securityTestPipeline()` returns instead of
 * assuming the installer ran.
 */
function completeDemoPipeline(): object
{
    $pipeline = securityTestPipeline();

    $stages = [
        'new' => 'New',
        'follow-up' => 'Follow Up',
        'prospect' => 'Prospect',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    $sortOrder = 1;

    foreach ($stages as $code => $name) {
        Stage::firstOrCreate(
            [
                'lead_pipeline_id' => $pipeline->id,
                'code' => $code,
            ],
            [
                'name' => $name,
                'probability' => 100,
                'sort_order' => $sortOrder,
            ]
        );

        $sortOrder++;
    }

    return $pipeline;
}

/**
 * The leads written by the seeder.
 *
 * A test database may already hold a lead from a fixture that was not wrapped
 * in a transaction, so the assertions never count the whole table.
 */
function demoLeadQuery(): Builder
{
    return Lead::query()->whereHas(
        'person',
        fn ($query) => $query->where('unique_id', 'like', '%@demo.klimiata.test%')
    );
}

it('seeds persons, leads, wallets and the call history', function () {
    completeDemoPipeline();

    $seeder = app(DemoDataSeeder::class);
    $seeder->fresh = true;
    $seeder->run();

    expect($seeder->skipped)->toBeFalse()
        ->and($seeder->createdPersons)->toBe(10)
        ->and($seeder->createdLeads)->toBe(15)
        ->and($seeder->createdWallets)->toBe(5)
        ->and($seeder->createdCallLogs)->toBe(8)
        ->and(Person::query()->where('unique_id', 'like', '%@demo.klimiata.test%')->count())->toBe(10)
        ->and(demoLeadQuery()->count())->toBe(15)
        ->and(CryptoWallet::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(5)
        ->and(CallLog::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(8);

    /** Every lead is reachable from the kanban and the contact card. */
    expect(demoLeadQuery()->whereNull('person_id')->count())->toBe(0)
        ->and(demoLeadQuery()->whereNull('lead_pipeline_stage_id')->count())->toBe(0)
        ->and((float) demoLeadQuery()->min('lead_value'))->toBe(100.0)
        ->and((float) demoLeadQuery()->max('lead_value'))->toBe(5000.0);

    /** The demo set covers both open and closed leads, so every widget has data. */
    expect(demoLeadQuery()->whereNotNull('closed_at')->count())->toBe(4)
        ->and(demoLeadQuery()->whereHas('stage', fn ($query) => $query->where('code', 'won'))->count())->toBe(2)
        ->and(demoLeadQuery()->whereHas('stage', fn ($query) => $query->where('code', 'lost'))->count())->toBe(2);

    expect(demoLeadQuery()->first()->activities)->not->toBeEmpty()
        ->and(Person::query()->where('unique_id', 'like', '%@demo.klimiata.test%')->first()->activities)->not->toBeEmpty();
});

it('skips a second run and replaces the rows only when fresh is requested', function () {
    completeDemoPipeline();

    $seeder = app(DemoDataSeeder::class);
    $seeder->fresh = true;

    $seeder->run();
    $seeder->fresh = false;
    $seeder->run();

    expect($seeder->skipped)->toBeTrue()
        ->and($seeder->createdPersons)->toBe(0)
        ->and($seeder->createdLeads)->toBe(0)
        ->and(demoLeadQuery()->count())->toBe(15)
        ->and(Person::query()->where('unique_id', 'like', '%@demo.klimiata.test%')->count())->toBe(10);

    $seeder->fresh = true;
    $seeder->run();

    expect($seeder->skipped)->toBeFalse()
        ->and($seeder->createdPersons)->toBe(10)
        ->and($seeder->createdLeads)->toBe(15)
        ->and(Person::query()->where('unique_id', 'like', '%@demo.klimiata.test%')->count())->toBe(10)
        ->and(demoLeadQuery()->count())->toBe(15)
        ->and(CryptoWallet::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(5)
        ->and(CallLog::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(8);
});

it('renders the lead kanban and the dashboard with the demo data', function () {
    completeDemoPipeline();

    $seeder = app(DemoDataSeeder::class);
    $seeder->fresh = true;
    $seeder->run();

    test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.leads.index'))
        ->assertOk();

    test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.dashboard.index'))
        ->assertOk();
});

it('exposes the seeder through an artisan command and can skip the calls', function () {
    completeDemoPipeline();

    $exitCode = Artisan::call('crypto-call-center:demo-data', ['--fresh' => true, '--no-calls' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('10 persons, 15 leads, 5 crypto wallets and 0 call logs')
        ->and(demoLeadQuery()->count())->toBe(15)
        ->and(CryptoWallet::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(5)
        ->and(CallLog::query()->whereIn('lead_id', demoLeadQuery()->select('id'))->count())->toBe(0);
});
