<?php

namespace Workdo\Timesheet\Entities;

use Illuminate\Database\Eloquent\Model;
use Workdo\Timesheet\Entities\Timesheet;
// 👇 importa el User real de tu app
use App\Models\User; // <-- AJUSTA si tu User vive en otro namespace

class TimesheetApproval extends Model
{
    protected $fillable = ['timesheet_id','user_id','action'];

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function user()
    {
        // 👇 fuerza el namespace correcto y la FK
        return $this->belongsTo(User::class, 'user_id');
    }
}
