<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    public function test_database_driver_matches_the_ci_job_expectation(): void
    {
        $expected = (string) env('PAATHNEXUS_EXPECT_DB_CONNECTION', 'sqlite');

        $this->assertSame($expected, DB::connection()->getDriverName());
    }
}
