<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * A field of the given entity, carrying the English name the installer ships.
 *
 * The parallel test databases are migrated but not seeded, so the shipped
 * attributes may be missing entirely; a seeded database is updated in place
 * instead of failing on the `code` + `entity_type` unique index. The `type` is
 * only given for the fixtures whose label the core formats - a date, say - and
 * otherwise follows the lookup type.
 */
function localizationAttribute(
    string $entityType,
    string $code,
    string $name,
    ?string $lookupType = null,
    ?string $type = null
): int {
    $values = [
        'name' => $name,
        'type' => $type ?? ($lookupType ? 'select' : 'text'),
        'lookup_type' => $lookupType,
        'is_user_defined' => 0,
    ];

    $id = DB::table('attributes')
        ->where('entity_type', $entityType)
        ->where('code', $code)
        ->value('id');

    if ($id) {
        DB::table('attributes')->where('id', $id)->update($values);

        return $id;
    }

    return DB::table('attributes')->insertGetId($values + [
        'entity_type' => $entityType,
        'code' => $code,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * The name of a lead field, as the database carries it.
 */
function localizationAttributeName(string $code): ?string
{
    return DB::table('attributes')
        ->where('entity_type', 'leads')
        ->where('code', $code)
        ->value('name');
}

/**
 * A source or a type option, carrying the English name the installer ships.
 */
function localizationOption(string $table, string $name): int
{
    $id = DB::table($table)->where('name', $name)->value('id');

    if ($id) {
        return $id;
    }

    return DB::table($table)->insertGetId([
        'name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * A system activity, in the shape `Traits\LogsActivity` stores it.
 *
 * The title and the payload are already translated around the field name -
 * only the name and the option label are frozen English, which is exactly what
 * the command has to rewrite.
 */
function localizationActivity(
    string $title,
    string $attribute,
    ?string $newLabel = null,
    ?string $oldLabel = null,
    mixed $newValue = 1
): int {
    return DB::table('activities')->insertGetId([
        'title' => $title,
        'type' => 'system',
        'additional' => json_encode([
            'new' => ['label' => $newLabel, 'value' => $newValue],
            'old' => ['label' => $oldLabel, 'value' => null],
            'attribute' => $attribute,
        ]),
        'is_done' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * A system activity attached to a lead, with the pipeline, source and type a
 * lead needs under the English names the installer ships.
 */
function localizationLeadActivity(
    string $title,
    string $attribute,
    ?string $newLabel = null,
    ?string $oldLabel = null,
    mixed $newValue = 1
): int {
    $sourceId = localizationOption('lead_sources', 'Phone');

    $typeId = localizationOption('lead_types', 'New Business');

    $pipelineId = DB::table('lead_pipelines')->where('name', 'Default Pipeline')->value('id');

    if (! $pipelineId) {
        $pipelineId = DB::table('lead_pipelines')->insertGetId([
            'name' => 'Default Pipeline',
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $stageId = DB::table('lead_pipeline_stages')
        ->where('lead_pipeline_id', $pipelineId)
        ->where('code', 'new')
        ->value('id');

    if (! $stageId) {
        $stageId = DB::table('lead_pipeline_stages')->insertGetId([
            'code' => 'new',
            'name' => 'New',
            'probability' => 100,
            'sort_order' => 1,
            'lead_pipeline_id' => $pipelineId,
        ]);
    }

    $personId = DB::table('persons')->insertGetId([
        'name' => 'Иван Петров',
        'emails' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $leadId = DB::table('leads')->insertGetId([
        'title' => 'Лид',
        'person_id' => $personId,
        'lead_source_id' => $sourceId,
        'lead_type_id' => $typeId,
        'lead_pipeline_id' => $pipelineId,
        'lead_pipeline_stage_id' => $stageId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $activityId = localizationActivity($title, $attribute, $newLabel, $oldLabel, $newValue);

    DB::table('lead_activities')->insert([
        'activity_id' => $activityId,
        'lead_id' => $leadId,
    ]);

    return $activityId;
}

it('translates the field names and the options the database carries', function () {
    localizationAttribute('leads', 'user_id', 'Sales Owner', 'users');
    localizationAttribute('leads', 'lead_source_id', 'Source', 'lead_sources');
    localizationAttribute('leads', 'expected_close_date', 'Expected Close Date');

    localizationOption('lead_sources', 'Phone');
    localizationOption('lead_types', 'New Business');

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Ответственный')
        ->and(localizationAttributeName('lead_source_id'))->toBe('Источник')
        ->and(localizationAttributeName('expected_close_date'))->toBe('Ожидаемая дата закрытия')
        ->and(DB::table('lead_sources')->where('name', 'Телефон')->exists())->toBeTrue()
        ->and(DB::table('lead_types')->where('name', 'Новый бизнес')->exists())->toBeTrue();
});

it('rewrites the field name and the option label frozen in the activity history', function () {
    localizationAttribute('leads', 'lead_source_id', 'Source', 'lead_sources');

    $activityId = localizationLeadActivity('Обновлено: Source', 'Source', 'Phone', null);

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    $activity = DB::table('activities')->where('id', $activityId)->first();

    $additional = json_decode($activity->additional, true);

    expect($activity->title)->toBe('Обновлено: Источник')
        ->and($additional['attribute'])->toBe('Источник')
        ->and($additional['new']['label'])->toBe('Телефон')
        ->and($additional['new']['value'])->toBe(1)
        ->and($additional['old']['label'])->toBeNull();
});

it('rewrites the date label the core froze in English', function () {
    localizationAttribute('leads', 'expected_close_date', 'Expected Close Date', null, 'date');

    $activityId = localizationLeadActivity(
        'Обновлено: Expected Close Date',
        'Expected Close Date',
        'Tue Oct 20, 2026',
        null,
        '2026-10-20'
    );

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    $activity = DB::table('activities')->where('id', $activityId)->first();

    $additional = json_decode($activity->additional, true);

    expect($activity->title)->toBe('Обновлено: Ожидаемая дата закрытия')
        ->and($additional['attribute'])->toBe('Ожидаемая дата закрытия')
        ->and($additional['new']['label'])->toBe('20.10.2026')
        ->and($additional['new']['value'])->toBe('2026-10-20');
});

it('leaves a date label the value cannot rebuild', function () {
    localizationAttribute('leads', 'expected_close_date', 'Expected Close Date', null, 'date');

    $activityId = localizationLeadActivity(
        'Обновлено: Expected Close Date',
        'Expected Close Date',
        'N/A',
        null,
        'not a date'
    );

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    $additional = json_decode(DB::table('activities')->where('id', $activityId)->value('additional'), true);

    expect($additional['new']['label'])->toBe('N/A');
});

it('is idempotent', function () {
    localizationAttribute('leads', 'user_id', 'Sales Owner', 'users');

    $activityId = localizationLeadActivity('Обновлено: Sales Owner', 'Sales Owner');

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    $firstRun = DB::table('activities')->where('id', $activityId)->first();

    $this->artisan('crypto-call-center:localize-catalog')
        ->expectsOutputToContain('field names: 0')
        ->expectsOutputToContain('lookup options: 0')
        ->expectsOutputToContain('activity history entries: 0')
        ->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Ответственный')
        ->and(DB::table('activities')->where('id', $activityId)->first())->toEqual($firstRun);
});

it('keeps a field an administrator renamed unless it is forced', function () {
    localizationAttribute('leads', 'user_id', 'Менеджер', 'users');

    $this->artisan('crypto-call-center:localize-catalog')
        ->expectsOutputToContain('Kept "Менеджер" for `leads.user_id`')
        ->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Менеджер');

    $this->artisan('crypto-call-center:localize-catalog', ['--force' => true])->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Ответственный');
});

it('writes nothing on a dry run', function () {
    localizationAttribute('leads', 'user_id', 'Sales Owner', 'users');

    $activityId = localizationLeadActivity('Обновлено: Sales Owner', 'Sales Owner');

    $this->artisan('crypto-call-center:localize-catalog', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Sales Owner')
        ->and(DB::table('activities')->where('id', $activityId)->value('title'))->toBe('Обновлено: Sales Owner');
});

it('renames the fields but leaves the history alone with --skip-history', function () {
    localizationAttribute('leads', 'user_id', 'Sales Owner', 'users');

    $activityId = localizationLeadActivity('Обновлено: Sales Owner', 'Sales Owner');

    $this->artisan('crypto-call-center:localize-catalog', ['--skip-history' => true])->assertSuccessful();

    expect(localizationAttributeName('user_id'))->toBe('Ответственный')
        ->and(DB::table('activities')->where('id', $activityId)->value('title'))->toBe('Обновлено: Sales Owner');
});

it('resolves a shared field name through the entity of the activity', function () {
    localizationAttribute('persons', 'name', 'Name');
    localizationAttribute('products', 'name', 'Name');

    $personId = DB::table('persons')->insertGetId([
        'name' => 'Иван Петров',
        'emails' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productId = DB::table('products')->insertGetId([
        'sku' => 'SKU-1',
        'name' => 'Товар',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $personActivityId = localizationActivity('Обновлено: Name', 'Name');

    DB::table('person_activities')->insert([
        'activity_id' => $personActivityId,
        'person_id' => $personId,
    ]);

    $productActivityId = localizationActivity('Обновлено: Name', 'Name');

    DB::table('product_activities')->insert([
        'activity_id' => $productActivityId,
        'product_id' => $productId,
    ]);

    $this->artisan('crypto-call-center:localize-catalog')->assertSuccessful();

    expect(DB::table('activities')->where('id', $personActivityId)->value('title'))->toBe('Обновлено: Имя')
        ->and(DB::table('activities')->where('id', $productActivityId)->value('title'))->toBe('Обновлено: Название');
});

it('skips an option whose Russian name already exists', function () {
    if (! DB::table('lead_pipelines')->where('name', 'Стандартная воронка')->exists()) {
        DB::table('lead_pipelines')->insert([
            'name' => 'Стандартная воронка',
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('lead_pipelines')->insert([
        'name' => 'Default Pipeline',
        'is_default' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('crypto-call-center:localize-catalog')
        ->expectsOutputToContain('Skipped `lead_pipelines` option "Default Pipeline": "Стандартная воронка" already exists.')
        ->assertSuccessful();

    expect(DB::table('lead_pipelines')->where('name', 'Default Pipeline')->exists())->toBeTrue()
        ->and(DB::table('lead_pipelines')->where('name', 'Стандартная воронка')->count())->toBe(1);
});
