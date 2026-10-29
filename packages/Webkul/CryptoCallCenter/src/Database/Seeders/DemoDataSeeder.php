<?php

namespace Webkul\CryptoCallCenter\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\CryptoCallCenter\Models\CallLog;
use Webkul\CryptoCallCenter\Repositories\CallLogRepository;
use Webkul\CryptoCallCenter\Repositories\CryptoWalletRepository;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Lead\Models\PipelineProxy;
use Webkul\Lead\Models\SourceProxy;
use Webkul\Lead\Models\StageProxy;
use Webkul\Lead\Models\TypeProxy;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\UserProxy;

/**
 * Demo content for a fresh Crypto Call Center installation.
 *
 * The rows are written through the core repositories, not with raw inserts, so
 * the custom attribute values, the `unique_id` of a person and the activity
 * entries Krayin creates on save stay exactly as they are on a real save. That
 * makes the seeded data usable by the lead kanban, the dashboard widgets and
 * the activity timeline instead of only filling the tables.
 *
 * Everything is scoped to the demo e-mail domain: a second run skips the
 * seeding, and `--fresh` (or `$fresh = true`) removes only these rows before it
 * writes them again. A developer's own leads are never touched.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Domain every seeded person is registered with.
     *
     * The e-mail is the marker of the demo data, so the seeder never needs a
     * separate bookkeeping table to find the rows it owns.
     */
    public const EMAIL_DOMAIN = 'demo.klimiata.test';

    /**
     * Stages a lead of the demo pipeline may be placed on.
     */
    protected const STAGE_CODES = [
        'new',
        'follow-up',
        'prospect',
        'negotiation',
        'won',
        'lost',
    ];

    /**
     * Remove the demo rows of an earlier run before seeding.
     */
    public bool $fresh = false;

    /**
     * Also seed the call history of some demo leads.
     */
    public bool $withCallLogs = true;

    /**
     * Whether the current run found the demo data already present.
     */
    public bool $skipped = false;

    /**
     * Number of rows written by the current run.
     */
    public int $createdPersons = 0;

    public int $createdLeads = 0;

    public int $createdWallets = 0;

    public int $createdCallLogs = 0;

    /**
     * Seed the demo content.
     */
    public function run(): void
    {
        $this->resetCounters();

        $userId = $this->resolveUserId();
        $pipeline = $this->resolvePipeline();
        $stages = $this->resolveStages($pipeline->id);

        $sourceIds = SourceProxy::modelClass()::query()->orderBy('id')->pluck('id')->all();
        $typeIds = TypeProxy::modelClass()::query()->orderBy('id')->pluck('id')->all();

        if ($sourceIds === [] || $typeIds === []) {
            throw new RuntimeException(
                'Demo data needs at least one lead source and one lead type. Run the Krayin installer seeders first.'
            );
        }

        if ($this->fresh) {
            $this->purgeDemoData();
        }

        if ($this->demoPersonIds() !== []) {
            $this->skipped = true;

            $this->warn('Demo data is already present and was left untouched. Pass --fresh to replace it.');

            return;
        }

        DB::transaction(function () use ($userId, $pipeline, $stages, $sourceIds, $typeIds): void {
            $persons = $this->createPersons($userId);

            $leads = $this->createLeads($userId, $pipeline->id, $stages, $sourceIds, $typeIds, $persons);

            $this->createWallets($leads);

            if ($this->withCallLogs) {
                $this->createCallLogs($leads, $userId);
            }
        });

        $this->info(sprintf(
            'Seeded %d persons, %d leads, %d crypto wallets and %d call logs.',
            $this->createdPersons,
            $this->createdLeads,
            $this->createdWallets,
            $this->createdCallLogs,
        ));
    }

    /**
     * Create the demo persons.
     *
     * @return array<int, object>
     */
    protected function createPersons(int $userId): array
    {
        $repository = app(PersonRepository::class);

        $persons = [];

        foreach (self::PERSONS as $definition) {
            $persons[] = $repository->create([
                'entity_type' => 'persons',
                'name' => $definition['name'],
                'emails' => [['label' => 'work', 'value' => $definition['email']]],
                'contact_numbers' => [['label' => 'mobile', 'value' => $definition['phone']]],
                'job_title' => $definition['job_title'],
                'user_id' => $userId,
            ]);

            $this->createdPersons++;
        }

        return $persons;
    }

    /**
     * Create the demo leads.
     *
     * The stage defines whether the lead is still open: a won or lost lead gets
     * a `closed_at` in the past, the open ones get an expected close date in
     * the future, so the dashboard and the kanban show a plausible picture.
     *
     * @param  array<int, object>  $persons
     * @param  array<int, int>  $sourceIds
     * @param  array<int, int>  $typeIds
     * @return array<int, object>
     */
    protected function createLeads(
        int $userId,
        int $pipelineId,
        Collection $stages,
        array $sourceIds,
        array $typeIds,
        array $persons
    ): array {
        $repository = app(LeadRepository::class);

        $leads = [];

        foreach (self::LEADS as $index => $definition) {
            $closedAt = isset($definition['closed_days_ago'])
                ? Carbon::now()->subDays($definition['closed_days_ago'])
                : null;

            $expectedCloseDate = $closedAt
                ? $closedAt->copy()->startOfDay()
                : Carbon::today()->addDays(7 + ($index % 14));

            $leads[] = $repository->create([
                'entity_type' => 'leads',
                'title' => $definition['title'],
                'description' => $definition['description'],
                'lead_value' => $definition['amount'],
                'status' => 1,
                'lost_reason' => $definition['lost_reason'] ?? null,
                'closed_at' => $closedAt?->toDateTimeString(),
                'expected_close_date' => $expectedCloseDate->toDateString(),
                'user_id' => $userId,
                'person_id' => $persons[$definition['person']]->id,
                'lead_source_id' => $sourceIds[$definition['source'] % count($sourceIds)],
                'lead_type_id' => $typeIds[$definition['type'] % count($typeIds)],
                'lead_pipeline_id' => $pipelineId,
                'lead_pipeline_stage_id' => $stages->get($definition['stage'])->id,
            ]);

            $this->createdLeads++;
        }

        return $leads;
    }

    /**
     * Attach the demo wallets to their leads.
     *
     * @param  array<int, object>  $leads
     */
    protected function createWallets(array $leads): void
    {
        $repository = app(CryptoWalletRepository::class);

        foreach (self::WALLETS as $definition) {
            $repository->create([
                'lead_id' => $leads[$definition['lead']]->id,
                'wallet_address' => $definition['address'],
                'network' => $definition['network'],
            ]);

            $this->createdWallets++;
        }
    }

    /**
     * Record a few conversations, so the call history screen has content too.
     *
     * @param  array<int, object>  $leads
     */
    protected function createCallLogs(array $leads, int $userId): void
    {
        $repository = app(CallLogRepository::class);

        foreach (self::CALL_LOGS as $index => $definition) {
            $leadIndex = $definition['lead'];

            $repository->create([
                'lead_id' => $leads[$leadIndex]->id,
                'user_id' => $userId,
                'phone_number' => self::PERSONS[self::LEADS[$leadIndex]['person']]['phone'],
                'duration' => $definition['duration'],
                'status' => $definition['status'],
                'transcript' => $definition['transcript'],
                'call_date' => Carbon::now()->subMinutes(5 + $index * 7)->toDateTimeString(),
            ]);

            $this->createdCallLogs++;
        }
    }

    /**
     * Resolve the owner of the demo rows: the first administrator, or the
     * first account when the installation has no role with full access.
     */
    protected function resolveUserId(): int
    {
        $userModel = UserProxy::modelClass();

        $user = $userModel::query()
            ->whereHas('role', fn ($query) => $query->where('permission_type', 'all'))
            ->orderBy('id')
            ->first()
            ?? $userModel::query()->orderBy('id')->first();

        if (! $user) {
            throw new RuntimeException(
                'Demo data needs an administrator account. Create one in the admin panel or run the Krayin installer seeders first.'
            );
        }

        return (int) $user->id;
    }

    /**
     * Resolve the pipeline the demo leads belong to.
     */
    protected function resolvePipeline(): object
    {
        $pipelineModel = PipelineProxy::modelClass();

        $pipeline = $pipelineModel::query()->where('is_default', 1)->orderBy('id')->first()
            ?? $pipelineModel::query()->orderBy('id')->first();

        if (! $pipeline) {
            throw new RuntimeException(
                'Demo data needs a lead pipeline. Run the Krayin installer seeders first.'
            );
        }

        return $pipeline;
    }

    /**
     * Resolve the pipeline stages and refuse to seed an incomplete pipeline.
     *
     * A stage that is missing would silently put its leads on a wrong column,
     * so the seeder reports the missing codes instead of guessing.
     *
     * @return Collection<string, object>
     */
    protected function resolveStages(int $pipelineId): Collection
    {
        $stages = StageProxy::modelClass()::query()
            ->where('lead_pipeline_id', $pipelineId)
            ->get()
            ->keyBy('code');

        $missing = array_diff(self::STAGE_CODES, $stages->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException(
                'The demo pipeline is missing the stages: '.implode(', ', $missing).'. Run the Krayin installer seeders first.'
            );
        }

        return $stages;
    }

    /**
     * Ids of the persons the seeder owns.
     *
     * A person is stored with `unique_id` as `user|organisation|email|phone`
     * (empty parts are dropped), so matching the address inside that column
     * locates a demo person without reading inside the JSON `emails` column.
     *
     * @return array<int, int>
     */
    protected function demoPersonIds(): array
    {
        return PersonProxy::modelClass()::query()
            ->where(function ($query): void {
                foreach (self::PERSONS as $person) {
                    $query->orWhere('unique_id', 'like', '%'.$person['email'].'%');
                }
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Remove the demo persons together with their leads.
     *
     * The leads go first: a lead owns the wallets and calls through cascading
     * foreign keys, and removing it explicitly keeps the purge correct even on
     * a database where the cascades are not enforced.
     */
    protected function purgeDemoData(): void
    {
        $personIds = $this->demoPersonIds();

        if ($personIds === []) {
            return;
        }

        LeadProxy::modelClass()::query()
            ->whereIn('person_id', $personIds)
            ->get()
            ->each(fn ($lead) => $lead->delete());

        PersonProxy::modelClass()::query()
            ->whereIn('id', $personIds)
            ->get()
            ->each(fn ($person) => $person->delete());
    }

    /**
     * Reset the counters of the previous run.
     *
     * The container reuses the instance when the seeder is run more than once
     * in the same process, so the second run would otherwise report the totals
     * of the first one.
     */
    protected function resetCounters(): void
    {
        $this->skipped = false;
        $this->createdPersons = 0;
        $this->createdLeads = 0;
        $this->createdWallets = 0;
        $this->createdCallLogs = 0;
    }

    /**
     * Write an informational line when a console command is attached.
     */
    protected function info(string $message): void
    {
        if (isset($this->command)) {
            $this->command->info($message);
        }
    }

    /**
     * Write a warning line when a console command is attached.
     */
    protected function warn(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn($message);
        }
    }

    /**
     * The people behind the demo leads.
     *
     * Every address carries EMAIL_DOMAIN, which is what marks the seeded rows.
     */
    protected const PERSONS = [
        [
            'name' => 'Алексей Морозов',
            'email' => 'alexey.morozov@demo.klimiata.test',
            'phone' => '+79001000101',
            'job_title' => 'Частный инвестор',
        ],
        [
            'name' => 'Мария Соколова',
            'email' => 'maria.sokolova@demo.klimiata.test',
            'phone' => '+79001000102',
            'job_title' => 'Финансовый аналитик',
        ],
        [
            'name' => 'Дмитрий Волков',
            'email' => 'dmitry.volkov@demo.klimiata.test',
            'phone' => '+79001000103',
            'job_title' => 'Владелец бизнеса',
        ],
        [
            'name' => 'Елена Кузнецова',
            'email' => 'elena.kuznetsova@demo.klimiata.test',
            'phone' => '+79001000104',
            'job_title' => 'Портфельный менеджер',
        ],
        [
            'name' => 'Игорь Лебедев',
            'email' => 'igor.lebedev@demo.klimiata.test',
            'phone' => '+79001000105',
            'job_title' => 'Трейдер',
        ],
        [
            'name' => 'Анна Павлова',
            'email' => 'anna.pavlova@demo.klimiata.test',
            'phone' => '+79001000106',
            'job_title' => 'HR-директор',
        ],
        [
            'name' => 'Сергей Новиков',
            'email' => 'sergey.novikov@demo.klimiata.test',
            'phone' => '+79001000107',
            'job_title' => 'IT-предприниматель',
        ],
        [
            'name' => 'Ольга Смирнова',
            'email' => 'olga.smirnova@demo.klimiata.test',
            'phone' => '+79001000108',
            'job_title' => 'Бухгалтер',
        ],
        [
            'name' => 'Павел Орлов',
            'email' => 'pavel.orlov@demo.klimiata.test',
            'phone' => '+79001000109',
            'job_title' => 'Врач',
        ],
        [
            'name' => 'Виктория Зайцева',
            'email' => 'victoria.zaytseva@demo.klimiata.test',
            'phone' => '+79001000110',
            'job_title' => 'Маркетолог',
        ],
    ];

    /**
     * The demo leads spread over the pipeline.
     *
     * `person`, `source` and `type` index into PERSONS and the lookup rows
     * resolved at run time (modulo, so one source is enough in a test
     * database). A won or lost lead carries `closed_days_ago`; every other
     * lead is still open.
     */
    protected const LEADS = [
        [
            'title' => 'BTC: накопление портфеля',
            'person' => 0,
            'stage' => 'new',
            'amount' => 2500,
            'source' => 0,
            'type' => 0,
            'description' => 'Клиент хочет вложить 2 500 $ в биткоин и обсудить график покупок.',
        ],
        [
            'title' => 'ETH: стейкинг-пакет',
            'person' => 1,
            'stage' => 'follow-up',
            'amount' => 1800,
            'source' => 1,
            'type' => 1,
            'description' => 'Интересуется стейкингом Ethereum, ждёт расчёт доходности за год.',
        ],
        [
            'title' => 'USDT: казначейство',
            'person' => 2,
            'stage' => 'negotiation',
            'amount' => 5000,
            'source' => 2,
            'type' => 0,
            'description' => 'Обсуждает размещение свободных средств компании в USDT.',
        ],
        [
            'title' => 'Altcoin: ревью портфеля',
            'person' => 3,
            'stage' => 'new',
            'amount' => 750,
            'source' => 3,
            'type' => 1,
            'description' => 'Нужен разбор текущего портфеля альткоинов и рекомендации.',
        ],
        [
            'title' => 'BTC: холодное хранение',
            'person' => 4,
            'stage' => 'prospect',
            'amount' => 1200,
            'source' => 0,
            'type' => 0,
            'description' => 'Планирует перевести биткоины на аппаратный кошелёк.',
        ],
        [
            'title' => 'DeFi: стратегия доходности',
            'person' => 5,
            'stage' => 'follow-up',
            'amount' => 3000,
            'source' => 4,
            'type' => 0,
            'description' => 'Сравнивает пулы ликвидности и просит оценить риски.',
        ],
        [
            'title' => 'ETH: валидатор под ключ',
            'person' => 6,
            'stage' => 'negotiation',
            'amount' => 4000,
            'source' => 1,
            'type' => 1,
            'description' => 'Готов запустить валидатор Ethereum, уточняет условия сопровождения.',
        ],
        [
            'title' => 'USDT: платежи для бизнеса',
            'person' => 7,
            'stage' => 'won',
            'amount' => 950,
            'source' => 2,
            'type' => 1,
            'closed_days_ago' => 3,
            'description' => 'Подключили приём USDT для расчётов с зарубежными партнёрами.',
        ],
        [
            'title' => 'Крипто-отчётность',
            'person' => 8,
            'stage' => 'new',
            'amount' => 500,
            'source' => 3,
            'type' => 0,
            'description' => 'Интересуется отчётностью по криптоактивам для налоговой.',
        ],
        [
            'title' => 'TRON: миграция кошелька',
            'person' => 9,
            'stage' => 'lost',
            'amount' => 350,
            'source' => 4,
            'type' => 1,
            'closed_days_ago' => 8,
            'lost_reason' => 'Выбрал другого провайдера',
            'description' => 'Обсуждали перенос TRON-кошелька и восстановление доступа.',
        ],
        [
            'title' => 'BTC: план DCA',
            'person' => 0,
            'stage' => 'prospect',
            'amount' => 1500,
            'source' => 1,
            'type' => 1,
            'description' => 'Планирует регулярные покупки биткоина на фиксированную сумму.',
        ],
        [
            'title' => 'ETH: стейкинг follow-up',
            'person' => 1,
            'stage' => 'follow-up',
            'amount' => 2200,
            'source' => 0,
            'type' => 1,
            'description' => 'Вернулась к вопросу стейкинга после роста курса.',
        ],
        [
            'title' => 'USDT: расчёты с партнёрами',
            'person' => 2,
            'stage' => 'won',
            'amount' => 4500,
            'source' => 2,
            'type' => 0,
            'closed_days_ago' => 1,
            'description' => 'Настроили корпоративный кошелёк для расчётов в USDT.',
        ],
        [
            'title' => 'Портфель: ребалансировка',
            'person' => 3,
            'stage' => 'new',
            'amount' => 100,
            'source' => 4,
            'type' => 0,
            'description' => 'Небольшой стартовый запрос на ребалансировку портфеля.',
        ],
        [
            'title' => 'Крипто-кастоди: онбординг',
            'person' => 4,
            'stage' => 'lost',
            'amount' => 3500,
            'source' => 3,
            'type' => 1,
            'closed_days_ago' => 12,
            'lost_reason' => 'Передумал из-за волатильности',
            'description' => 'Рассматривал кастодиальное хранение крупной суммы.',
        ],
    ];

    /**
     * Crypto wallets of five demo leads, one network each.
     *
     * The addresses are well-formed examples, not real wallets.
     */
    protected const WALLETS = [
        [
            'lead' => 0,
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'network' => 'bitcoin',
        ],
        [
            'lead' => 1,
            'address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network' => 'ethereum',
        ],
        [
            'lead' => 2,
            'address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            'network' => 'tron',
        ],
        [
            'lead' => 6,
            'address' => '0x9a8f7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f00',
            'network' => 'ethereum',
        ],
        [
            'lead' => 12,
            'address' => 'TXFBqBbqJommqZf7BV8NNYzePh97UmJodJ',
            'network' => 'tron',
        ],
    ];

    /**
     * Conversations for the call history screen.
     *
     * A missed or failed call has no talk time and no transcript, the same way
     * the telephony integration stores it.
     */
    protected const CALL_LOGS = [
        [
            'lead' => 0,
            'duration' => 245,
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Обсудили сумму входа и график покупок BTC.',
        ],
        [
            'lead' => 1,
            'duration' => 0,
            'status' => CallLog::STATUS_MISSED,
            'transcript' => null,
        ],
        [
            'lead' => 2,
            'duration' => 620,
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Согласовали размещение средств компании в USDT.',
        ],
        [
            'lead' => 5,
            'duration' => 180,
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Клиент попросил сравнение пулов ликвидности.',
        ],
        [
            'lead' => 6,
            'duration' => 0,
            'status' => CallLog::STATUS_FAILED,
            'transcript' => null,
        ],
        [
            'lead' => 7,
            'duration' => 340,
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Подтвердили подключение приёма USDT.',
        ],
        [
            'lead' => 12,
            'duration' => 780,
            'status' => CallLog::STATUS_COMPLETED,
            'transcript' => 'Настроили корпоративный кошелёк и доступы.',
        ],
        [
            'lead' => 9,
            'duration' => 0,
            'status' => CallLog::STATUS_MISSED,
            'transcript' => null,
        ],
    ];
}
