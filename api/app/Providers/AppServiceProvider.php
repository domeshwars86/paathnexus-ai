<?php

namespace App\Providers;

use App\AI\AiProvider;
use App\AI\OpenAiCompatibleProvider;
use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, function (): AiProvider {
            if (config('ai.provider') !== 'openai_compatible') {
                throw new RuntimeException('The configured AI provider is not supported.');
            }
            return new OpenAiCompatibleProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn ($user, string $token): string => rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));
        \Illuminate\Auth\Notifications\VerifyEmail::createUrlUsing(fn ($user): string => \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.v1.auth.verify-email', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));
        \Illuminate\Support\Facades\RateLimiter::for('ai', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by((string) $request->user()?->id)
        );
    }
}
