<?php

use Illuminate\Database\Seeder;
use App\Models\Permission;

class TimesheetPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Permission::firstOrCreate(
            ['name' => 'timesheet validate', 'guard_name' => 'web'],
            ['display_name' => 'Approve timesheets', 'module' => 'Timesheet']
        );
        \App\Models\Permission::firstOrCreate(
            ['name' => 'timesheet unvalidate', 'guard_name' => 'web'],
            ['display_name' => 'Unapprove timesheets', 'module' => 'Timesheet']
        );
    }
}
