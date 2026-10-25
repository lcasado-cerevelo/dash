<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpdeskTicketAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'changed_by',
        'field_name',
        'old_value',
        'new_value',
    ];

    public function ticket()
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}