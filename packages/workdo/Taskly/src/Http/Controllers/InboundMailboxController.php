<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Workdo\Taskly\Entities\TicketInboundMailbox;

class InboundMailboxController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer',
            'username' => 'required|string|max:255',
            'password_plain' => 'nullable|string',
            'folder' => 'required|string|max:255',
            'processed_folder' => 'nullable|string|max:255',
        ]);

        TicketInboundMailbox::create([
            'name' => $request->name,
            'imap_host' => $request->imap_host,
            'imap_port' => $request->imap_port,
            'imap_ssl' => $request->boolean('imap_ssl'),
            'username' => $request->username,
            'password_enc' => Crypt::encryptString((string) $request->password_plain),
            'folder' => $request->folder,
            'processed_folder' => $request->processed_folder,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('Inbound mailbox created successfully.'));
    }

    public function update(Request $request, TicketInboundMailbox $mailbox)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer',
            'username' => 'required|string|max:255',
            'password_plain' => 'nullable|string',
            'folder' => 'required|string|max:255',
            'processed_folder' => 'nullable|string|max:255',
        ]);

        $data = [
            'name' => $request->name,
            'imap_host' => $request->imap_host,
            'imap_port' => $request->imap_port,
            'imap_ssl' => $request->boolean('imap_ssl'),
            'username' => $request->username,
            'folder' => $request->folder,
            'processed_folder' => $request->processed_folder,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password_plain')) {
            $data['password_enc'] = Crypt::encryptString((string) $request->password_plain);
        }

        $mailbox->update($data);

        return back()->with('success', __('Inbound mailbox updated successfully.'));
    }

    public function destroy(TicketInboundMailbox $mailbox)
    {
        $mailbox->delete();

        return back()->with('success', __('Inbound mailbox deleted successfully.'));
    }
}