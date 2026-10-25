<?php

namespace Workdo\Timesheet\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TimesheetInboundSenderMap extends Model
{
    protected $table = 'timesheet_inbound_sender_maps';

    protected $fillable = [
        'mailbox_id',
        'match_type',
        'match_value',
        'employee_user_id',
        'project_id',
        'task_id',
        'customer_id',
        'customer_client_id',
        'workspace_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mailbox()
    {
        return $this->belongsTo(TimesheetInboundMailbox::class, 'mailbox_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function project()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'project_id');
    }

    public function task()
    {
        return $this->belongsTo(\Workdo\Taskly\Entities\Task::class, 'task_id');
    }

    public function customer()
    {
        return $this->belongsTo(\Workdo\Account\Entities\Customer::class, 'customer_id');
    }

    public function customerClient()
    {
        return $this->belongsTo(\Workdo\Account\Entities\CustomerClient::class, 'customer_client_id');
    }
}
