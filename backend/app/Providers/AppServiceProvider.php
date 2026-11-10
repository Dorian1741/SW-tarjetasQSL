<?php

namespace App\Providers;

use App\Services\Callbook\HamQthCallbookProvider;
use App\Services\Callbook\QrzCallbookProvider;
use App\Services\CallsignLookupService;
use App\Services\CallsignService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([QrzCallbookProvider::class, HamQthCallbookProvider::class], 'callbook.providers');
        $this->app->bind(CallsignLookupService::class, fn ($app) => new CallsignLookupService($app->make(CallsignService::class), $app->tagged('callbook.providers')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('callbook', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));
    }
}
