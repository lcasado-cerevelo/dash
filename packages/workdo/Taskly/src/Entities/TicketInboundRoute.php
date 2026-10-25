<?php

namespace Workdo\Taskly\Entities;

use Illuminate\Database\Eloquent\Model;

class TicketInboundRoute extends Model
{
    protected $table = 'ticket_inbound_routes';

    protected $fillable = [
        'mailbox_id',
        'project_id',
        'to_email',
        'to_contains',
        'from_contains',
        'subject_contains',
        'allow_new_ticket',
        'sort_order',
        'is_active',
    ];

    public function mailbox()
    {
        return $this->belongsTo(TicketInboundMailbox::class, 'mailbox_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}