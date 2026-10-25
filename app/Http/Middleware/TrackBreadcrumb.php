<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrackBreadcrumb
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Solo páginas GET normales (no AJAX/JSON)
        if ($request->isMethod('GET') && !$request->ajax() && !$request->wantsJson()) {

            // Páginas que NO deben actualizar el “último”
            $ignore = [
                'login', 'logout', 'password.*', 'verification.*',
                // evita formularios/acciones “transitorias”
                '*.create', '*.create.*', '*.edit', '*.edit.*',
                'ignition.*', 'sanctum.*',
            ];
            if ($request->route() && $request->routeIs($ignore)) {
                return $response;
            }

            // Base “estable” para comparar: nombre de ruta o path sin query
            $routeName = optional($request->route())->getName();
            $base      = $routeName ?: trim($request->path(), '/');

            // URL completa para volver (sí incluye query)
            $url = $request->fullUrl();

            // Label “bonito”
            $label = $this->labelFor($routeName, $base);

            $current = [
                'url'   => $url,
                'name'  => $routeName,         // p. ej. roles.index
                'path'  => $base,              // p. ej. roles
                'label' => $label,             // p. ej. Roles
            ];

            // Mueve el “current” anterior a “last” solo si cambió de pantalla base
            $prev = session('crumb.current');
            $prevBase = $prev['name'] ?? $prev['path'] ?? null;

            if ($prev && $prevBase !== $base) {
                session(['crumb.last' => $prev]);
            }

            session(['crumb.current' => $current]);
        }

        return $response;
    }

    private function labelFor(?string $routeName, string $base): string
    {
        // Mapa opcional para nombres “bonitos” por ruta
        $map = [
            'roles.index'   => __('Roles'),
            'users.index'   => __('Users'),
            'timesheet.index' => __('Timesheets'),
            // …
        ];

        if ($routeName && isset($map[$routeName])) {
            return $map[$routeName];
        }

        // Fallback: último segmento del path/routename capitalizado
        $raw = $routeName
            ? (str_contains($routeName, '.') ? Str::afterLast($routeName, '.') : $routeName)
            : (str_contains($base, '/') ? Str::afterLast($base, '/') : $base);

        return Str::title(str_replace(['-', '_'], ' ', $raw));
    }
}
