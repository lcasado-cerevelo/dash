<?php

namespace Workdo\Timesheet\Entities;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;

class TimesheetUtility extends Model
{
    public static function GivePermissionToRoles($role_id = null, $rolename = 'staff'): void
    {
        $staff_permissions = [
            'timesheet manage',
            'timesheet create',
            'timesheet edit',
            'timesheet delete',
            'timesheet validate',
        ];

        $rolesQuery = Role::query();
        if ($role_id !== null) {
            $rolesQuery->where('id', $role_id);
        }
        if (!empty($rolename)) {
            $rolesQuery->where('name', $rolename);
        }

        $roles = $rolesQuery->get();
        if ($roles->isEmpty()) {
            return;
        }

        $guard = $roles->first()->guard_name ?? config('auth.defaults.guard', 'web');

        foreach ($roles as $role) {
            foreach ($staff_permissions as $permName) {
                // Garantiza que el permiso exista y con el guard correcto
                $permission = Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => $guard]
                );

                if (!$role->hasPermission($permName)) {
                    $role->givePermission($permission); // modelo válido, no null
                }
            }
        }
    }
}
