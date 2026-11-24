<?php

namespace App\Providers;

use App\Support\EnvCheck;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Checked when serving web requests (console commands such as package:discover
        // must still work before .env exists, e.g. during install or CI).
        if (! $this->app->runningInConsole()) {
            EnvCheck::assert();
        }

        // Spot N+1 queries while developing: log them instead of breaking the page.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(fn ($model, string $relation) => logger()->warning('N+1 lazy load', ['model' => $model::class, 'relation' => $relation]));

        // Every signed-in change (POST/PUT/PATCH/DELETE) shares one generous per-user budget.
        RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
