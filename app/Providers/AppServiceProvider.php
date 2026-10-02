<?php

namespace App\Providers;

use App\Services\ArtEngine;
use App\Support\AnimationCatalog;
use App\Support\Gallery;
use App\Support\ServiceCatalog;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ServiceCatalog::class, fn (): ServiceCatalog => new ServiceCatalog);
        $this->app->singleton(AnimationCatalog::class, fn (): AnimationCatalog => new AnimationCatalog);
        $this->app->singleton(Gallery::class, fn (): Gallery => new Gallery);
        $this->app->singleton(ArtEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['partials.header', 'partials.footer'], NavigationComposer::class);
    }
}
