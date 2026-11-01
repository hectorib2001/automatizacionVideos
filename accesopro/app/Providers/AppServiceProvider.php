<?php

namespace App\Providers;

use App\Services\Publishing\DrivePublisher;
use App\Services\Publishing\Publisher;
use App\Services\Publishing\YouTube\GoogleYouTubeApi;
use App\Services\Publishing\YouTube\YouTubeApi;
use App\Services\Publishing\YouTubePublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(YouTubeApi::class, GoogleYouTubeApi::class);

        // Destino del pipeline automático (job Publish). El botón "Subir a YouTube" de la
        // tabla de control usa YouTubePublisher siempre, sea cual sea este valor.
        $this->app->bind(Publisher::class, fn ($app) => match (config('accesopro.publish_target')) {
            'youtube' => $app->make(YouTubePublisher::class),
            default => $app->make(DrivePublisher::class),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
