<?php

return [
    // No crear usuarios de prueba
    'create_users' => false,

    // No vaciar tablas en producción
    'truncate_tables' => false,

    /*
     | Roles de tu app. Ajusta/añade los que uses realmente.
     | Las letras se mapean en 'permissions_map' abajo.
     */
    'roles_structure' => [
        'super admin' => [
            // CRUD + manage + validate + unvalidate
            'timesheet' => 'm,c,r,u,d,v,x',
            // deja estos si ya los usas; si no, bórralos
            'users'     => 'c,r,u,d',
            'payments'  => 'c,r,u,d',
            'profile'   => 'r,u',
        ],

        'company' => [
            'timesheet' => 'm,c,r,u,d,v,x',
            'profile'   => 'r,u',
        ],

        'employee' => [
            // empleados crean/leen/actualizan los suyos, sin validar en lote
            'timesheet' => 'c,r,u',
            'profile'   => 'r,u',
        ],

        // Ejemplo de otro rol si existiera
        // 'client' => [
        //     'timesheet' => 'r',
        // ],
    ],

    /*
     | Mapeo letra → acción. Añadimos extras a CRUD.
     | El seeder construirá permisos como "<módulo>-<acción>" (con guion).
     */
    'permissions_map' => [
        'm' => 'manage',
        'c' => 'create',
        'r' => 'read',
        'u' => 'update',
        'd' => 'delete',
        'v' => 'validate',
        'x' => 'unvalidate',
    ],
];
