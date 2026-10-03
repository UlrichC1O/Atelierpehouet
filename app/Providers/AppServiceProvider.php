<?php

namespace App\Providers;

use App\Cms\Cms;
use App\Cms\DatabaseHealth;
use App\Cms\DatabaseMigrator;
use App\Cms\Media\MediaManager;
use App\Cms\Media\MediaStorage;
use App\Cms\Media\MediaStorageManager;
use App\Cms\OverridingTranslationLoader;
use App\Models\User;
use App\Services\ArtEngine;
use App\Support\AnimationCatalog;
use App\Support\Gallery;
use App\Support\ServiceCatalog;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The admin CMS (docs/CMS.md §4): the public read model, the services overlay, the photo library,
        // the database circuit breaker and the database updates (§13 A1, E23).
        $this->app->singleton(Cms::class);
        $this->app->singleton(DatabaseHealth::class);
        $this->app->singleton(DatabaseMigrator::class);
        $this->app->singleton(ServiceCatalog::class, fn (Application $app): ServiceCatalog => new ServiceCatalog(null, $app->make(Cms::class)));
        $this->app->singleton(MediaStorageManager::class);
        $this->app->bind(MediaStorage::class, fn (Application $app): MediaStorage => $app->make(MediaStorageManager::class)->driver());
        $this->app->singleton(MediaManager::class);

        // Texts edited in the CMS over the lang/ files. The translation provider is deferred: the
        // extender is kept until the translator first resolves its loader, then wraps it.
        $this->app->extend('translation.loader', fn (Loader $loader, Application $app): Loader => new OverridingTranslationLoader(
            $loader,
            fn (): Cms => $app->make(Cms::class),
        ));

        // What these singletons remember belongs to one request (memoized snapshot, the breaker's
        // in-request flag, the migrations read): forgotten when the next request comes in — tests and
        // long-running workers serve several requests with one container.
        $this->app->rebinding('request', static function (Application $app): void {
            foreach ([Cms::class, DatabaseHealth::class, DatabaseMigrator::class] as $service) {
                if ($app->resolved($service)) {
                    $app->make($service)->reset();
                }
            }
        });

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

        // The explicit right to use the admin CMS (docs/CMS.md §13 D18). While the is_admin column
        // does not exist yet (migrations pending, e.g. production's owner before the first update),
        // every account is let in: the owner must be able to reach Maintenance and run the update.
        Gate::define('admin', static fn (User $user): bool => ! array_key_exists('is_admin', $user->getAttributes())
            || $user->is_admin === true);

        // Password reset links lead to the admin's own form (docs/CMS.md §13 D19).
        ResetPassword::createUrlUsing(static fn (CanResetPassword $user, string $token): string => route('admin.password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
