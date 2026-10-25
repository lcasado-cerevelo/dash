<?php

namespace Workdo\Timesheet\Providers;

use Illuminate\Support\ServiceProvider;
use Workdo\Timesheet\Services\TimesheetEmailService;

class TimesheetServiceProvider extends ServiceProvider
{
    protected $moduleName      = 'Timesheet';
    protected $moduleNameLower = 'timesheet';

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);   // <-- ya registra CompanySettingListener

        // Singleton del servicio de email
        $this->app->singleton(TimesheetEmailService::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'timesheet');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerTranslations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\TimesheetFetchInboundEmail::class,
            ]);
        }
    }

    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(__DIR__ . '/../Resources/lang');
        }
    }
}
