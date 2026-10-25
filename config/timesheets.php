<?php

return [
    'allowed_days_back' => env('TIMESHEET_DAYS_BACK', 3),
    'allowed_days_forward' => env('TIMESHEET_DAYS_FORWARD', 0),
    'override_hours' => env('TIMESHEET_OVERRIDE_HOURS', 24),
];