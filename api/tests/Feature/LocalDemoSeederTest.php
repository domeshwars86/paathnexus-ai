<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_does_not_create_an_unconfigured_test_account(): void
    {
        $this->withEnvironment('PAATHNEXUS_DEMO_SEED', null, function (): void {
            $this->seed(DatabaseSeeder::class);

            $this->assertDatabaseCount('users', 0);
        });
    }

    public function test_database_seeder_refuses_demo_data_outside_local_environment(): void
    {
        $this->app['env'] = 'testing';
        $this->withEnvironment('PAATHNEXUS_DEMO_SEED', 'true', function (): void {
            try {
                $this->seed(DatabaseSeeder::class);
                $this->fail('The demo seeder must refuse non-local environments.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('APP_ENV=local', $exception->getMessage());
            }
        });

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_local_demo_seed_requires_a_private_password_before_writing_data(): void
    {
        $this->app['env'] = 'local';
        $this->withEnvironment('PAATHNEXUS_DEMO_SEED', 'true', function (): void {
            $this->withEnvironment('PAATHNEXUS_DEMO_PASSWORD', null, function (): void {
                try {
                    $this->seed(LocalDemoSeeder::class);
                    $this->fail('The demo seeder must require a configured password.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('password', $exception->errors());
                }
            });
        });

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_local_demo_seed_creates_synthetic_workspaces_and_is_idempotent(): void
    {
        $this->app['env'] = 'local';
        $this->withEnvironment('PAATHNEXUS_DEMO_SEED', 'true', function (): void {
            $this->withEnvironment('PAATHNEXUS_DEMO_PASSWORD', 'LocalDemoPassphrase123', function (): void {
                $this->seed(LocalDemoSeeder::class);
                $this->seed(LocalDemoSeeder::class);
            });
        });

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('organizations', 2);
        $this->assertDatabaseCount('organization_user', 5);
        $this->assertDatabaseCount('student_profiles', 2);
        $this->assertDatabaseCount('courses', 2);
        $this->assertDatabaseCount('course_lessons', 3);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertDatabaseCount('fee_invoices', 1);
        $this->assertDatabaseCount('guardian_student_links', 1);
        $this->assertDatabaseCount('workspace_notifications', 4);

        $owner = User::query()->where('email', 'owner@demo.paathnexus.test')->firstOrFail();
        $this->assertTrue(Hash::check('LocalDemoPassphrase123', $owner->password));
        $this->assertSame(2, $owner->organizations()->wherePivot('status', 'active')->count());
        $this->assertDatabaseHas('courses', ['code' => 'DISCOVERY-01', 'is_public' => true]);
        $this->assertDatabaseHas('course_lessons', ['title' => 'Observe a local ecosystem']);
    }

    private function withEnvironment(string $key, ?string $value, callable $callback): void
    {
        $originalValue = getenv($key);
        $hadEnv = array_key_exists($key, $_ENV);
        $originalEnv = $_ENV[$key] ?? null;
        $hadServer = array_key_exists($key, $_SERVER);
        $originalServer = $_SERVER[$key] ?? null;

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            if ($originalValue === false) putenv($key);
            else putenv($key.'='.$originalValue);
            if ($hadEnv) $_ENV[$key] = $originalEnv;
            else unset($_ENV[$key]);
            if ($hadServer) $_SERVER[$key] = $originalServer;
            else unset($_SERVER[$key]);
        }
    }
}
