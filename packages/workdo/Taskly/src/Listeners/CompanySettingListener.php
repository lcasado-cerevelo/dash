<?php

namespace Workdo\Taskly\Listeners;

use App\Events\CompanySettingEvent;

class CompanySettingListener
{
    public function handle(CompanySettingEvent $event): void
    {
        $module = 'Taskly';
        $methodName = 'index';
        $controllerClass = "Workdo\\Taskly\\Http\\Controllers\\Company\\SettingsController";

        if (class_exists($controllerClass)) {
            $controller = \App::make($controllerClass);

            if (method_exists($controller, $methodName)) {
                $html = $event->html;
                $settings = $html->getSettings();
                $output = $controller->{$methodName}($settings);

                $html->add([
                    'html' => $output->toHtml(),
                    'order' => 1600,
                    'module' => $module,
                    'permission' => '',
                ]);
            }
        }
    }
}