<?php

namespace Workdo\Taskly\Entities;

use Illuminate\Database\Eloquent\Model;

class TicketInboundMailbox extends Model
{
    protected $table = 'ticket_inbound_mailboxes';

    protected $fillable = [
        'name',
        'imap_host',
        'imap_port',
        'imap_ssl',
        'username',
        'password_enc',
        'folder',
        'processed_folder',
        'is_active',
    ];

    public function routes()
    {
        return $this->hasMany(TicketInboundRoute::class, 'mailbox_id');
    }

    public function projectMaps()
    {
        return $this->hasMany(TicketInboundProjectMap::class, 'mailbox_id');
    }
}