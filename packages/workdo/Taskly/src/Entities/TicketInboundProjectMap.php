<?php

namespace Workdo\Taskly\Entities;

use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Entities\Customer;
use Workdo\Account\Entities\CustomerClient;
use App\Models\HelpdeskTicketCategory;

class TicketInboundProjectMap extends Model
{
    protected $table = 'ticket_inbound_project_maps';

    protected $fillable = [
        'mailbox_id',
        'match_type',
        'match_value',
        'match_name',
        'customer_id',
        'customer_client_id',
        'task_id',
        'project_id',
        'workspace_id',
        'category_id',
        'priority',
        'assign_to',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mailbox()
    {
        return $this->belongsTo(TicketInboundMailbox::class, 'mailbox_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function customerClient()
    {
        return $this->belongsTo(CustomerClient::class, 'customer_client_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function category()
    {
        return $this->belongsTo(HelpdeskTicketCategory::class, 'category_id');
    }
}