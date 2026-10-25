<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Classes\Module;
use App\Http\Middleware\TrackBreadcrumb;  

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('module', function ($app) {
            return new Module();
        });
       
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app('router')->pushMiddlewareToGroup('web', TrackBreadcrumb::class);
    }
}
