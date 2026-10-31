<?php

namespace App\Providers;

use App\Services\Publishing\DrivePublisher;
use App\Services\Publishing\Publisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PUBLISH_TARGET=youtube activará YouTubePublisher cuando exista (Fase 5).
        $this->app->bind(Publisher::class, DrivePublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
