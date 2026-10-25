<?php

use Illuminate\Support\Str;

if (!function_exists('crumb_label')) {
    function crumb_label(array $crumb): string
    {
        // Mapa opcional: route name => etiqueta
        $map = config('breadcrumbs.map', [
            'dashboard'       => 'Dashboard',
            'roles.index'     => 'Roles',
            'users.index'     => 'Users',
            'timesheet.index' => 'Timesheet',
            // agrega las que quieras “bonitas”
        ]);

        $name = $crumb['name'] ?? null;
        if ($name && isset($map[$name])) {
            return $map[$name];
        }

        // Fallback: humaniza el nombre de ruta o path
        $base = $name ?: ($crumb['path'] ?? '');
        return Str::title(str_replace(['.', '-', '/'], ' ', $base));
    }
}
