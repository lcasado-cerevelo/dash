<?php

namespace Workdo\Timesheet\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use Workdo\Timesheet\Entities\TimesheetApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Timesheet\Entities\Timesheet;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TimesheetApprovalController extends BaseController
{
    protected function canValidate(): bool
    {
        $u = Auth::user();
        return $u->hasRole('super admin') || $u->hasPermission('timesheet validate');
    }

    protected function canUnvalidate(): bool
    {
        $u = Auth::user();
        return $u->hasRole('super admin') || $u->hasPermission('timesheet unvalidate');
    }

    // --- BULK ---
public function bulkValidate(Request $request)
{
    abort_unless($this->canValidate(), 403);

    $pwd = (string) $request->input('password', '');
    if ($pwd === '' || !Hash::check($pwd, Auth::user()->getAuthPassword())) {
        return response()->json(['message' => __('Incorrect password.')], 422);
    }

    $ids = (array) $request->input('ids', []);
    if (empty($ids)) return response()->json(['message'=>'No items.'], 422);

    $userId = Auth::id();
    $updated = 0;

    \DB::transaction(function () use ($ids, $userId, &$updated) {
        $rows = Timesheet::whereIn('id', $ids)
            ->where(function($q){ $q->where('validated', 0)->orWhereNull('validated'); })
            ->when(
                Schema::hasColumn((new Timesheet)->getTable(), 'status'),
                fn($q) => $q->where(function($qq){
                    $qq->whereNull('status')->orWhere('status', 'pending');
                })
            )
            ->lockForUpdate()
            ->get();

        foreach ($rows as $t) {
            $t->validated = 1;
            if (isset($t->status)) $t->status = 'approved';
            $t->save();

            TimesheetApproval::create([
                'timesheet_id' => $t->id,
                'user_id'      => $userId,
                'action'       => 'approved',
            ]);
            $updated++;
        }
    });

    return response()->json(['message' => __('Selected timesheets approved successfully.'), 'updated' => $updated]);
}

public function bulkUnvalidate(Request $request)
{
    abort_unless($this->canUnvalidate(), 403);

    $pwd = (string) $request->input('password', '');
    if ($pwd === '' || !Hash::check($pwd, Auth::user()->getAuthPassword())) {
        return response()->json(['message' => __('Incorrect password.')], 422);
    }

    $ids = (array) $request->input('ids', []);
    if (empty($ids)) return response()->json(['message'=>'No items.'], 422);

    $userId = Auth::id();
    $updated = 0;

    \DB::transaction(function () use ($ids, $userId, &$updated) {
        $rows = Timesheet::whereIn('id', $ids)
            ->where('validated', 1)
            ->where(function($q){ $q->where('invoiced', 0)->orWhereNull('invoiced'); })
            ->where(function($q){ $q->where('billed',   0)->orWhereNull('billed'); })
            ->where(function($q){ $q->where('paid',     0)->orWhereNull('paid'); })
            ->lockForUpdate()
            ->get();

        foreach ($rows as $t) {
            $t->validated = 0;
            if (isset($t->status)) $t->status = 'pending';
            $t->save();

            TimesheetApproval::create([
                'timesheet_id' => $t->id,
                'user_id'      => $userId,
                'action'       => 'unapproved',
            ]);
            $updated++;
        }
    });

    return response()->json(['message' => __('Selected timesheets unapproved successfully.'), 'updated' => $updated]);
}

    // --- UNITARIO ---
public function validateOne(Request $request, Timesheet $timesheet)
{
    abort_unless($this->canValidate(), 403);

    // VALIDAR CONTRASEÑA MANUALMENTE y responder JSON 422 SIEMPRE en error
    $pwd = (string) $request->input('password', '');
    if ($pwd === '' || !Hash::check($pwd, Auth::user()->getAuthPassword())) {
        return response()->json(['message' => __('Incorrect password.')], 422);
    }

    if ((int)$timesheet->validated === 1) {
        return response()->json(['message' => __('Timesheet is already approved.')], 422);
    }

    \DB::transaction(function () use ($timesheet) {
        $timesheet->validated = 1;
        if (Schema::hasColumn($timesheet->getTable(), 'status')) {
            $timesheet->status = 'approved';
        }
        $timesheet->save();

        \Workdo\Timesheet\Entities\TimesheetApproval::create([
            'timesheet_id' => $timesheet->id,
            'user_id'      => Auth::id(),
            'action'       => 'approved',
        ]);
    });

    // ÉXITO EN JSON
    return response()->json(['message' => __('Timesheet approved.')]);
}

public function unvalidateOne(Request $request, Timesheet $timesheet)
{
    abort_unless($this->canUnvalidate(), 403);

    $pwd = (string) $request->input('password', '');
    if ($pwd === '' || !Hash::check($pwd, Auth::user()->getAuthPassword())) {
        return response()->json(['message' => __('Incorrect password.')], 422);
    }

    if ((int)$timesheet->validated !== 1) {
        return response()->json(['message' => __('Timesheet is not approved.')], 422);
    }
    if (($timesheet->invoiced ?? 0) || ($timesheet->billed ?? 0) || ($timesheet->paid ?? 0)) {
        return response()->json(['message' => __('You cannot unapprove a timesheet that is invoiced, billed, or paid.')], 422);
    }

    \DB::transaction(function () use ($timesheet) {
        $timesheet->validated = 0;
        if (Schema::hasColumn($timesheet->getTable(), 'status')) {
            $timesheet->status = 'pending';
        }
        $timesheet->save();

        \Workdo\Timesheet\Entities\TimesheetApproval::create([
            'timesheet_id' => $timesheet->id,
            'user_id'      => Auth::id(),
            'action'       => 'unapproved',
        ]);
    });

    return response()->json(['message' => __('Timesheet unapproved.')]);
}
    
public function verifyPassword(Request $request)
{
    // Asegúrate de que el usuario está autenticado
    if (!Auth::check()) {
        return response()->json(['message' => 'Not authenticated'], 401);
    }

    $pwd = (string) $request->input('password', '');

    // Trim — evita problemas con espacios invisibles
    $pwd = trim($pwd);

    if ($pwd === '') {
        return response()->json(['message' => 'Password is required'], 422);
    }

    $user = Auth::user();

    // Comprueba usando Hash::check sobre el password del usuario actual
    if (!Hash::check($pwd, $user->password)) {
        return response()->json(['message' => 'Incorrect password'], 422);
    }

    return response()->json(['message' => 'OK']);
}    
}
