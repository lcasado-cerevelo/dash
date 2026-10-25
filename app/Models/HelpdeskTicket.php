<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\Taskly\Entities\Task;
use Workdo\Account\Entities\Customer;
use Workdo\Account\Entities\CustomerClient;

class HelpdeskTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'task_id',
        'customer_id',
        'customer_client_id',
        'name',
        'email',
        'category',
        'subject',
        'status',
        'priority',
        'description',
        'attachments',
        'note',
        'user_id',
        'visible_to_user_ids',
        'touched_by_user_ids',
        'workspace',
        'created_by',
    ];

    protected $casts = [
        'visible_to_user_ids' => 'array',
        'touched_by_user_ids' => 'array',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'status' => 'New',
        'priority' => 3,
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function conversions()
    {
        return $this->hasMany(HelpdeskConversion::class, 'ticket_id', 'id')->orderBy('id');
    }

    public function tcategory()
    {
        return $this->hasOne(HelpdeskTicketCategory::class, 'id', 'category');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function customerClient()
    {
        return $this->belongsTo(CustomerClient::class, 'customer_client_id');
    }

    public function audits()
    {
        return $this->hasMany(HelpdeskTicketAudit::class, 'ticket_id');
    }
    
    public function parentTicket()
    {
        return $this->belongsTo(HelpdeskTicket::class, 'parent_ticket_id');
    }
    
    public function childTickets()
    {
        return $this->hasMany(HelpdeskTicket::class, 'parent_ticket_id');
    }
}