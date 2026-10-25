<?php

namespace Workdo\Timesheet\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','project_id','task_id','date',
        'hours','minutes','description','notes',
        'workspace_id','created_by','type',
        'validated','invoiced','paid',
        'from_time','to_time','customer_id',
        'customer_client_id','ticket_id'
    ];

    protected $casts = [
        'validated' => 'boolean',
        'invoiced'  => 'boolean',
        'paid'      => 'boolean',
        'ticket_id' => 'integer',
    ];

    protected static function newFactory()
    {
        return \Workdo\Timesheet\Database\factories\TimesheetFactory::new();
    }

    // FK: timesheets.user_id -> users.id
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    // FK: timesheets.project_id -> projects.id
    public function project()
    {
        // Adjust class if your Project model lives elsewhere
        return $this->belongsTo(\Workdo\Taskly\Entities\Project::class, 'project_id', 'id');
        // e.g. return $this->belongsTo(\Workdo\Project\Entities\Project::class, 'project_id');
    }

    // FK: timesheets.customer_client_id -> customer_clients.id
    public function customerClient()
    {
        // Adjust class/namespace to your actual CustomerClient model
        return $this->belongsTo(\Workdo\Account\Entities\CustomerClient::class, 'customer_client_id', 'id');
        // e.g. return $this->belongsTo(\Workdo\Customer\Entities\CustomerClient::class, 'customer_client_id');
    }

    // (Optional) If you also use customer_id
    public function customer()
    {
        return $this->belongsTo(\Workdo\Account\Entities\Customer::class, 'customer_id', 'id');
    }

    public function ticket()
    {
        return $this->belongsTo(\App\Models\HelpdeskTicket::class, 'ticket_id', 'id');
    }
    
    public function approvals()
    {
        return $this->hasMany(\Workdo\Timesheet\Entities\TimesheetApproval::class);
    }
}
