<?php

namespace App\Providers;

use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Behind a proxy/tunnel, trust its X-Forwarded-* headers so URLs use https.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Header store selector state for the signed-in layout.
        View::composer(['layouts.app', 'layouts::app'], function ($view) {
            if ($user = auth()->user()) {
                $view->with('storeContext', StoreContext::for($user));
            }
        });
    }
}
