<?php

namespace Workdo\Timesheet\Entities;

use Illuminate\Database\Eloquent\Model;

class TimesheetInboundMailbox extends Model
{
    protected $table = 'timesheet_inbound_mailboxes';

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

    protected $casts = [
        'imap_ssl'  => 'boolean',
        'is_active' => 'boolean',
    ];

    public function senderMaps()
    {
        return $this->hasMany(TimesheetInboundSenderMap::class, 'mailbox_id');
    }
}
