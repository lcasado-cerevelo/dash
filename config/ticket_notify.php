<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notificaciones automáticas de tickets (php artisan tickets:notify)
    |--------------------------------------------------------------------------
    */

    // Correos en copia de los correos 1 y 2 (separados por coma). Ej: "jarvis@x.com,luis@y.com"
    'cc' => array_values(array_filter(array_map('trim', explode(',', (string) env('TICKET_NOTIFY_CC', ''))))),

    // Destinatarios del resumen del viernes (separados por coma).
    'friday_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('TICKET_NOTIFY_FRIDAY_TO', ''))))),

    // MODO PRUEBA: si se define, todos los correos (de cualquier tipo) van SOLO a esta dirección, sin copias.
    'test_to' => env('TICKET_NOTIFY_TEST_TO'),

    // Días en Validation antes del primer recordatorio, y cada cuántos días se repite.
    'reminder_days' => (int) env('TICKET_NOTIFY_REMINDER_DAYS', 7),

    // Vigencia en días del botón "Validar" de los recordatorios.
    'link_days' => (int) env('TICKET_NOTIFY_LINK_DAYS', 7),

    // Estados que se consideran cerrados.
    'closed_statuses' => ['Closed', 'Canceled'],

    // Estado "pendiente por aprobar" y estado al que pasa al validar.
    'validation_status' => 'Validation',
    'validated_status' => 'Closed',

    // Servicio de envío: brevo (por defecto) o sendgrid.
    'driver' => env('TICKET_NOTIFY_DRIVER', 'brevo'),

    // Brevo (API v3). El remitente debe estar validado en Brevo (Senders, Domains & Dedicated IPs).
    'brevo' => [
        'api_key'      => env('BREVO_API_KEY'),
        'from_address' => env('BREVO_FROM_ADDRESS', 'no-reply@cerevelo.com'),
        'from_name'    => env('BREVO_FROM_NAME', 'Notification Service'),
    ],

    // SendGrid (mismas variables que ya usa EmailTemplate). Workspace 5 = InnovaSyst.
    'sendgrid' => [
        'default' => [
            'api_key'      => env('SENDGRID_API_KEY'),
            'from_address' => env('SENDGRID_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'from_name'    => env('SENDGRID_FROM_NAME', env('MAIL_FROM_NAME')),
        ],
        'innova' => [
            'api_key'      => env('SENDGRID_API_KEY_INNOVA'),
            'from_address' => env('SENDGRID_FROM_ADDRESS_INNOVA', env('MAIL_FROM_ADDRESS_INNOVA')),
            'from_name'    => env('SENDGRID_FROM_NAME_INNOVA', env('MAIL_FROM_NAME_INNOVA')),
        ],
    ],

    // Archivo (dentro de storage/app) donde se recuerda lo ya enviado. No usa la base de datos.
    'state_file' => 'ticket-notify-state.json',
];
