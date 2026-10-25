<?php

exec('php artisan optimize:clear');
exec('php artisan config:clear');
exec('php artisan cache:clear');

echo "Cleared!";