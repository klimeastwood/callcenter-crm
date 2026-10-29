<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LocalizeCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto-call-center:localize-catalog
                            {--force : Rename a field even when its shipped English name was changed by hand}
                            {--dry-run : Report the planned renames without writing anything}
                            {--skip-history : Leave the labels already frozen in the activity history untouched}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate the field names and lookup options Krayin reads from the database';

    /**
     * The pivot tables that attach a system activity to the entity it describes.
     *
     * The activity itself does not carry its entity, and the label of the field
     * it mentions lives in `attributes` per entity type, so the pivot is what
     * tells "Name" of a product from "Name" of a warehouse.
     */
    protected const ACTIVITY_PIVOTS = [
        'leads' => 'lead_activities',
        'persons' => 'person_activities',
        'products' => 'product_activities',
        'warehouses' => 'warehouse_activities',
    ];

    /**
     * How many field names were rewritten.
     */
    protected int $renamedAttributes = 0;

    /**
     * How many lookup options were rewritten.
     */
    protected int $renamedOptions = 0;

    /**
     * How many activity history entries were rewritten.
     */
    protected int $rewrittenActivities = 0;

    /**
     * Execute the console command.
     *
     * The command is idempotent on purpose: it is meant to be re-run after
     * every deploy, import or fresh install, and a row that already carries its
     * Russian text is skipped. A field an administrator renamed in
     * Settings → Attributes keeps its own wording unless `--force` is given.
     */
    public function handle(): int
    {
        /**
         * The container reuses the command instance when it is executed more
         * than once in a single process (`Artisan::call()` in a loop, tests),
         * so the counters of the previous run must not leak into this one.
         */
        $this->renamedAttributes = 0;

        $this->renamedOptions = 0;

        $this->rewrittenActivities = 0;

        if (! Schema::hasTable('attributes')) {
            $this->error('The `attributes` table is missing: run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $attributes = (array) config('crypto_call_center.localization.attributes', []);

        $lookups = (array) config('crypto_call_center.localization.lookups', []);

        if ($attributes === []) {
            $this->error('No field names are declared in `crypto_call_center.localization.attributes`.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing is written, the lines below are the plan.');
        }

        $this->translateAttributes($attributes);

        $this->translateLookups($lookups);

        if ($this->option('skip-history')) {
            $this->line('Skipped the activity history: --skip-history was given.');
        } elseif (Schema::hasTable('activities')) {
            $this->translateHistory($attributes, $lookups);
        }

        $this->newLine();

        $this->info($this->option('dry-run') ? 'Planned renames:' : 'Renamed:');

        $this->line("  field names: {$this->renamedAttributes}");

        $this->line("  lookup options: {$this->renamedOptions}");

        $this->line("  activity history entries: {$this->rewrittenActivities}");

        $this->comment('Re-run the command after an import or a fresh install; a row that already carries the Russian text is skipped.');

        return self::SUCCESS;
    }

    /**
     * Rename the attribute of every field the catalog declares.
     *
     * A missing row means the entity is not installed, which is not an error,
     * and a row that no longer carries the shipped English name was changed by
     * an administrator - the catalog cannot know whether their wording should
     * win, so it only reports the drift.
     */
    protected function translateAttributes(array $catalog): void
    {
        foreach ($catalog as $entityType => $fields) {
            foreach ((array) $fields as $code => $names) {
                $english = $names['en'] ?? null;

                $russian = $names['ru'] ?? null;

                if (! is_string($english) || ! is_string($russian) || $english === '' || $russian === '') {
                    $this->warn("Skipped `{$entityType}.{$code}`: both an `en` and a `ru` name are required.");

                    continue;
                }

                $attribute = DB::table('attributes')
                    ->where('entity_type', $entityType)
                    ->where('code', $code)
                    ->first(['id', 'name']);

                if (! $attribute || $attribute->name === $russian) {
                    continue;
                }

                if ($attribute->name !== $english && ! $this->option('force')) {
                    $this->warn("Kept \"{$attribute->name}\" for `{$entityType}.{$code}`: it was renamed by hand. Pass --force to replace it with \"{$russian}\".");

                    continue;
                }

                $this->write('attributes', ['name' => $russian], ['id' => $attribute->id]);

                $this->renamedAttributes++;

                $this->line("Attribute `{$entityType}.{$code}`: \"{$attribute->name}\" → \"{$russian}\"");
            }
        }
    }

    /**
     * Rename the options of the shipped lookup tables.
     *
     * The rows are matched by their English name, so an option that was renamed
     * by hand is left alone and an option a previous run already translated is
     * simply not found again.
     */
    protected function translateLookups(array $catalog): void
    {
        foreach ($catalog as $table => $options) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                $this->warn("Skipped `{$table}`: the table or its `name` column is not installed.");

                continue;
            }

            foreach ((array) $options as $english => $russian) {
                if (! is_string($russian) || $russian === '') {
                    continue;
                }

                $count = DB::table($table)->where('name', $english)->count();

                if ($count === 0) {
                    continue;
                }

                /**
                 * A row that already carries the Russian name - an option the
                 * administrator created before the command was run - would make
                 * the update violate the unique name of the table (`lead_pipelines`
                 * is unique on it), so the option is reported instead of merged:
                 * pointing every reference at one row is a decision a human has
                 * to make.
                 */
                if (DB::table($table)->where('name', $russian)->exists()) {
                    $this->warn("Skipped `{$table}` option \"{$english}\": \"{$russian}\" already exists.");

                    continue;
                }

                $this->write($table, ['name' => $russian], ['name' => $english]);

                $this->renamedOptions += $count;

                $this->line("`{$table}` option \"{$english}\" → \"{$russian}\" ({$count} row(s))");
            }
        }
    }

    /**
     * Rewrite the labels a saved entity has frozen into its activity history.
     *
     * `Traits\LogsActivity` stores the resolved field name and the resolved
     * option label into `activities.title` and `activities.additional` on save,
     * so the history of a lead edited before the rename would keep printing
     * English forever. Only system activities are touched, and only the parts
     * of the payload the catalog actually names.
     */
    protected function translateHistory(array $attributes, array $lookups): void
    {
        $meta = $this->attributeMeta($attributes);

        DB::table('activities')
            ->where('type', 'system')
            ->whereNotNull('additional')
            ->select(['id', 'title', 'additional'])
            ->chunkById(500, function (Collection $activities) use ($attributes, $meta, $lookups): void {
                $entities = $this->activityEntities($activities->pluck('id')->all());

                foreach ($activities as $activity) {
                    $this->translateActivity($activity, $attributes, $lookups, $meta, $entities);
                }
            });
    }

    /**
     * Rewrite one history entry, if the field it mentions is in the catalog.
     */
    protected function translateActivity(
        object $activity,
        array $attributes,
        array $lookups,
        array $meta,
        array $entities
    ): void {
        $additional = json_decode((string) $activity->additional, true);

        if (! is_array($additional)) {
            return;
        }

        $stored = $additional['attribute'] ?? null;

        if (! is_string($stored) || $stored === '') {
            return;
        }

        $entry = $this->catalogEntry($attributes, $entities[$activity->id] ?? null, $stored);

        if ($entry === null) {
            return;
        }

        $changed = false;

        if ($stored !== $entry['ru']) {
            $additional['attribute'] = $entry['ru'];

            $changed = true;
        }

        $changed = $this->translateOptionLabels($additional, $entry, $lookups, $meta) || $changed;

        $changed = $this->translateValueLabels($additional, $entry, $meta) || $changed;

        $title = (string) $activity->title;

        $renamedTitle = $this->translateTitle($title, $stored, $entry);

        if ($renamedTitle !== null) {
            $title = $renamedTitle;

            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $this->write('activities', [
            'title' => $title,
            'additional' => json_encode($additional),
        ], ['id' => $activity->id]);

        $this->rewrittenActivities++;

        $this->line($stored === $entry['ru']
            ? "Activity #{$activity->id}: labels of \"{$entry['ru']}\" refreshed"
            : "Activity #{$activity->id}: \"{$stored}\" → \"{$entry['ru']}\"");
    }

    /**
     * Replace the option label stored for the changed field.
     *
     * A number or a user name carries no lookup option, so only the
     * `new` / `old` labels of a field whose lookup table the catalog knows are
     * rewritten - and only when the stored label still matches the English one.
     */
    protected function translateOptionLabels(array &$additional, array $entry, array $lookups, array $meta): bool
    {
        $lookupType = $meta[$entry['entity']][$entry['code']]['lookup_type'] ?? null;

        if (! is_string($lookupType) || ! isset($lookups[$lookupType])) {
            return false;
        }

        $labels = (array) $lookups[$lookupType];

        $changed = false;

        foreach (['new', 'old'] as $side) {
            $label = $additional[$side]['label'] ?? null;

            if (! is_string($label) || ! isset($labels[$label]) || ! is_string($labels[$label])) {
                continue;
            }

            $additional[$side]['label'] = $labels[$label];

            $changed = true;
        }

        return $changed;
    }

    /**
     * Replace the date label the core froze in English.
     *
     * `getAttributeLabel()` formats a date with `format('D M d, Y')` and a
     * datetime with `format('D M d, Y H:i A')` before the label is stored, so a
     * history entry keeps printing "Tue Oct 20, 2026" after the field itself was
     * translated. The raw value is in the payload, which makes the label
     * rebuildable: dates are rewritten from it in the day-first form a Russian
     * screen expects, and anything the parser rejects keeps its stored label.
     */
    protected function translateValueLabels(array &$additional, array $entry, array $meta): bool
    {
        $type = $meta[$entry['entity']][$entry['code']]['type'] ?? null;

        if (! in_array($type, ['date', 'datetime'], true)) {
            return false;
        }

        $changed = false;

        foreach (['new', 'old'] as $side) {
            $label = $this->dateLabel($additional[$side]['value'] ?? null, $type);

            if ($label === null || ($additional[$side]['label'] ?? null) === $label) {
                continue;
            }

            $additional[$side]['label'] = $label;

            $changed = true;
        }

        return $changed;
    }

    /**
     * Format a stored date value the way the Russian screens show it.
     */
    protected function dateLabel(mixed $value, string $type): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->format($type === 'datetime' ? 'd.m.Y H:i' : 'd.m.Y');
    }

    /**
     * Rename the field at the end of a stored history title.
     *
     * The title is built as "Updated: {attribute}" from a translated line, so
     * the prefix is already Russian while the field name is frozen English.
     * Both the stored name and the shipped English one are tried, because a
     * previous run may have rewritten the payload before the title, and only a
     * trailing match is replaced so a comment cannot be damaged.
     */
    protected function translateTitle(string $title, string $stored, array $entry): ?string
    {
        foreach (array_unique([$stored, $entry['en']]) as $name) {
            if ($name === $entry['ru'] || ! str_ends_with($title, $name)) {
                continue;
            }

            return mb_substr($title, 0, mb_strlen($title) - mb_strlen($name)).$entry['ru'];
        }

        return null;
    }

    /**
     * Resolve the catalog entry a history entry refers to.
     *
     * The entity is taken from the pivot that attaches the activity. An
     * activity whose entity is gone - the pivot row cascades away with it -
     * falls back to a name the catalog declares exactly once, so the shared
     * "Name" and "Description" are never guessed onto the wrong entity.
     *
     * @return array{entity: string, code: string, en: string, ru: string}|null
     */
    protected function catalogEntry(array $attributes, ?string $entityType, string $name): ?array
    {
        if ($entityType !== null) {
            foreach ((array) ($attributes[$entityType] ?? []) as $code => $names) {
                if (in_array($name, [$names['en'] ?? null, $names['ru'] ?? null], true)) {
                    return [
                        'entity' => $entityType,
                        'code' => $code,
                        'en' => $names['en'],
                        'ru' => $names['ru'],
                    ];
                }
            }

            return null;
        }

        $matches = [];

        foreach ($attributes as $entity => $fields) {
            foreach ((array) $fields as $code => $names) {
                if (in_array($name, [$names['en'] ?? null, $names['ru'] ?? null], true)) {
                    $matches[$entity.'.'.$code] = [
                        'entity' => $entity,
                        'code' => $code,
                        'en' => $names['en'],
                        'ru' => $names['ru'],
                    ];
                }
            }
        }

        return count($matches) === 1 ? reset($matches) : null;
    }

    /**
     * The type and lookup table of every field the catalog declares.
     *
     * @return array<string, array<string, array{type: string, lookup_type: string|null}>>
     */
    protected function attributeMeta(array $attributes): array
    {
        $meta = [];

        foreach (array_keys($attributes) as $entityType) {
            $meta[$entityType] = DB::table('attributes')
                ->where('entity_type', $entityType)
                ->get(['code', 'type', 'lookup_type'])
                ->mapWithKeys(fn ($attribute) => [
                    $attribute->code => [
                        'type' => $attribute->type,
                        'lookup_type' => $attribute->lookup_type,
                    ],
                ])
                ->all();
        }

        return $meta;
    }

    /**
     * Map the activity ids of a chunk onto the entity each one describes.
     *
     * @return array<int, string>
     */
    protected function activityEntities(array $activityIds): array
    {
        $entities = [];

        foreach (self::ACTIVITY_PIVOTS as $entityType => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $ids = DB::table($table)
                ->whereIn('activity_id', $activityIds)
                ->distinct()
                ->pluck('activity_id');

            foreach ($ids as $activityId) {
                $entities[$activityId] ??= $entityType;
            }
        }

        return $entities;
    }

    /**
     * Write a change, unless the run only reports the plan.
     */
    protected function write(string $table, array $values, array $conditions): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table($table)->where($conditions)->update($values);
    }
}
