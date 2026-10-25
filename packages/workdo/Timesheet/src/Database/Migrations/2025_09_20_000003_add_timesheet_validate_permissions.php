<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $permTable   = config('laratrust.tables.permissions', 'permissions');
        $roleTable   = config('laratrust.tables.roles', 'roles');
        $pivotTable  = config('laratrust.tables.permission_role', 'permission_role');

        $now = now();
        // Crea permisos (con guion)
        DB::table($permTable)->insertOrIgnore([
            ['name'=>'timesheet-validate',   'display_name'=>'Timesheet: Validate',   'created_at'=>$now,'updated_at'=>$now],
            ['name'=>'timesheet-unvalidate', 'display_name'=>'Timesheet: Unvalidate', 'created_at'=>$now,'updated_at'=>$now],
        ]);

        // Adjunta a super admin y company
        $roleIds = DB::table($roleTable)->whereIn('name', ['super admin','company'])->pluck('id','name');
        $permIds = DB::table($permTable)->whereIn('name',['timesheet-validate','timesheet-unvalidate'])->pluck('id','name');

        $rows = [];
        foreach (['super admin','company'] as $r) {
            foreach (['timesheet-validate','timesheet-unvalidate'] as $p) {
                if (isset($roleIds[$r], $permIds[$p])) {
                    $row = ['permission_id'=>$permIds[$p], 'role_id'=>$roleIds[$r]];
                    if (Schema::hasColumn($pivotTable, 'team_id')) $row['team_id'] = null; // si tu pivot usa teams
                    $rows[] = $row;
                }
            }
        }
        foreach ($rows as $row) {
            DB::table($pivotTable)->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        $permTable = config('laratrust.tables.permissions', 'permissions');
        DB::table($permTable)->whereIn('name',['timesheet-validate','timesheet-unvalidate'])->delete();
    }
};
