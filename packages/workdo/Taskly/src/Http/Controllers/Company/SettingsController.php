<?php

namespace Workdo\Taskly\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Workdo\Taskly\Entities\TicketInboundMailbox;

class SettingsController extends Controller
{
    public function index($settings = null)
    {
        $mailboxes = TicketInboundMailbox::orderBy('name')->get();

        return view('taskly::setting.inbound_mailboxes', compact('mailboxes', 'settings'));
    }
}