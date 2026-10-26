<?php

namespace Coderflex\LaravelTicket\Commands;

use Coderflex\LaravelTicket\Demo\DeskDemoSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'tickets:seed-demo';

    protected $description = 'Load the demo departments, catalog, staff, and sample tickets';

    public function handle(DeskDemoSeeder $seeder): int
    {
        $seeder->seedSamples();

        $this->info('Demo desk is ready. Sign in as ada@example.com with the demo password.');

        return self::SUCCESS;
    }
}
