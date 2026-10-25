<?php

namespace Workdo\Timesheet\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as Provider;
use App\Events\CompanyMenuEvent;
use App\Events\CompanySettingEvent;
use App\Events\GivePermissionToRole;
use Workdo\Timesheet\Listeners\CompanyMenuListener;
use Workdo\Timesheet\Listeners\CompanySettingListener;
use Workdo\Timesheet\Listeners\GiveRoleToPermission;

class EventServiceProvider extends Provider
{
    protected $listen = [
        CompanyMenuEvent::class => [
            CompanyMenuListener::class,
        ],
        // Inyecta el bloque de configuración en /company/settings
        CompanySettingEvent::class => [
            CompanySettingListener::class,
        ],
        GivePermissionToRole::class => [
            GiveRoleToPermission::class,
        ],
    ];

    protected function discoverEventsWithin()
    {
        return [
            __DIR__ . '/../Listeners',
        ];
    }
}
