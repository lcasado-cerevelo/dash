<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "START\n";

try {
    require __DIR__ . '/vendor/autoload.php';
    echo "autoload OK\n";

    $app = require_once __DIR__ . '/bootstrap/app.php';
    echo "bootstrap OK\n";

    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "kernel OK\n";

    $secret = '123456';

    if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }

    echo "before artisan\n";

    \Illuminate\Support\Facades\Artisan::call('timesheet:debug-email', [
        '--mailbox' => 1,
        '--limit' => 10,
    ]);

    echo "artisan executed\n\n";
    echo \Illuminate\Support\Facades\Artisan::output();

} catch (\Throwable $e) {
    echo "ERROR:\n";
    echo $e->getMessage() . "\n\n";
    echo "FILE: " . $e->getFile() . "\n";
    echo "LINE: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
}

echo "\nEND";
echo "</pre>";