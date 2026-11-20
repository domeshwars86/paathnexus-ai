<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! filter_var(env('PAATHNEXUS_DEMO_SEED', false), FILTER_VALIDATE_BOOL)) return;

        if (! app()->environment('local')) {
            throw new RuntimeException('PaathNexus demo data can only be seeded when APP_ENV=local.');
        }

        $this->call(LocalDemoSeeder::class);
    }
}
