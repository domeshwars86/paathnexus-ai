<?php

namespace App\Console\Commands;

use App\Services\PlatformUsageMetrics;
use Illuminate\Console\Command;

class CapturePlatformUsageSnapshot extends Command
{
    protected $signature = 'platform:capture-usage-snapshot';

    protected $description = 'Capture daily aggregate platform usage counts';

    public function handle(PlatformUsageMetrics $metrics): int
    {
        $snapshot = $metrics->capture();
        $this->info('Captured platform usage for '.$snapshot->snapshot_date->toDateString().'.');

        return self::SUCCESS;
    }
}
