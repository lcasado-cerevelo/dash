<?php

namespace Workdo\Timesheet\Listeners;

use App\Events\CompanySettingEvent;

/**
 * Inyecta el bloque de configuración de timesheet-por-email en la página
 * global de Settings de la empresa, exactamente igual que hace Taskly.
 *
 * El bloque aparece como una card dentro de /company/settings.
 */
class CompanySettingListener
{
    public function handle(CompanySettingEvent $event): void
    {
        $module = 'Timesheet';

        if (!in_array($module, $event->html->modules)) {
            return;
        }

        $controllerClass = \Workdo\Timesheet\Http\Controllers\Company\SettingsController::class;

        if (!class_exists($controllerClass)) {
            return;
        }

        $controller = \App::make($controllerClass);

        if (!method_exists($controller, 'index')) {
            return;
        }

        $html     = $event->html;
        $settings = $html->getSettings();
        $output   = $controller->index($settings);

        $html->add([
            'html'       => $output->toHtml(),
            'order'      => 1450,   // justo después del bloque de Taskly (1600)
            'module'     => $module,
            'permission' => 'timesheet manage',
        ]);
    }
}
