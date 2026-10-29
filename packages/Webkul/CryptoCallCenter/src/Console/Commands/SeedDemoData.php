<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Illuminate\Console\Command;
use Webkul\CryptoCallCenter\Database\Seeders\DemoDataSeeder;

class SeedDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto-call-center:demo-data
                            {--fresh : Remove the demo rows of an earlier run before seeding}
                            {--no-calls : Do not seed the demo call history}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo persons, leads, crypto wallets and call logs of the CryptoCallCenter package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $seeder = app(DemoDataSeeder::class);

        $seeder->fresh = (bool) $this->option('fresh');
        $seeder->withCallLogs = ! $this->option('no-calls');
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);

        $seeder->run();

        return self::SUCCESS;
    }
}
