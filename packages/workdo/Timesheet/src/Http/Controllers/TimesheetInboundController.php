<?php

namespace Workdo\Timesheet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Entities\TimesheetInboundSenderMap;

/**
 * Maneja el CRUD de mailboxes y sender maps para la feature de
 * timesheets por email. Las rutas apuntan aquí; la vista vive en
 * company/settings/inbound_mailboxes.blade.php inyectada via Settings.
 */
class TimesheetInboundController extends Controller
{
    // ── Mailboxes ─────────────────────────────────────────────────────────────

    public function storeMailbox(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'name'             => 'required|string|max:255',
            'imap_host'        => 'required|string|max:255',
            'imap_port'        => 'required|integer|min:1|max:65535',
            'username'         => 'required|string|max:255',
            'password_plain'   => 'required|string',
            'folder'           => 'required|string|max:255',
            'processed_folder' => 'nullable|string|max:255',
        ]);

        TimesheetInboundMailbox::create([
            'name'             => $request->name,
            'imap_host'        => $request->imap_host,
            'imap_port'        => (int) $request->imap_port,
            'imap_ssl'         => $request->boolean('imap_ssl'),
            'username'         => $request->username,
            'password_enc'     => Crypt::encryptString((string) $request->password_plain),
            'folder'           => $request->folder ?: 'INBOX',
            'processed_folder' => $request->processed_folder ?: null,
            'is_active'        => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', __('Mailbox created successfully.'));
    }

    public function updateMailbox(Request $request, TimesheetInboundMailbox $mailbox)
    {
        $this->authorizeManage();

        $request->validate([
            'name'             => 'required|string|max:255',
            'imap_host'        => 'required|string|max:255',
            'imap_port'        => 'required|integer|min:1|max:65535',
            'username'         => 'required|string|max:255',
            'password_plain'   => 'nullable|string',
            'folder'           => 'required|string|max:255',
            'processed_folder' => 'nullable|string|max:255',
        ]);

        $data = [
            'name'             => $request->name,
            'imap_host'        => $request->imap_host,
            'imap_port'        => (int) $request->imap_port,
            'imap_ssl'         => $request->boolean('imap_ssl'),
            'username'         => $request->username,
            'folder'           => $request->folder ?: 'INBOX',
            'processed_folder' => $request->processed_folder ?: null,
            'is_active'        => $request->boolean('is_active'),
        ];

        if ($request->filled('password_plain')) {
            $data['password_enc'] = Crypt::encryptString((string) $request->password_plain);
        }

        $mailbox->update($data);

        return redirect()->back()->with('success', __('Mailbox updated successfully.'));
    }

    public function destroyMailbox(TimesheetInboundMailbox $mailbox)
    {
        $this->authorizeManage();
        $mailbox->delete(); // cascade borra los senderMaps (FK nullOnDelete, los deja huérfanos)
        return redirect()->back()->with('success', __('Mailbox deleted.'));
    }

    // ── Sender Maps ───────────────────────────────────────────────────────────

    public function storeSenderMap(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'match_type'         => 'required|in:email,domain',
            'match_value'        => 'required|string|max:255',
            'mailbox_id'         => 'nullable|integer|exists:timesheet_inbound_mailboxes,id',
            'employee_user_id'   => 'nullable|integer|exists:users,id',
            'project_id'         => 'nullable|integer',
            'task_id'            => 'nullable|integer',
            'customer_id'        => 'nullable|integer',
            'customer_client_id' => 'nullable|integer',
            'sort_order'         => 'nullable|integer',
        ]);

        TimesheetInboundSenderMap::create([
            'mailbox_id'         => $request->filled('mailbox_id')         ? (int) $request->mailbox_id         : null,
            'match_type'         => $request->match_type,
            'match_value'        => strtolower(trim($request->match_value)),
            'employee_user_id'   => $request->filled('employee_user_id')   ? (int) $request->employee_user_id   : null,
            'project_id'         => $request->filled('project_id')         ? (int) $request->project_id         : null,
            'task_id'            => $request->filled('task_id')            ? (int) $request->task_id            : null,
            'customer_id'        => $request->filled('customer_id')        ? (int) $request->customer_id        : null,
            'customer_client_id' => $request->filled('customer_client_id') ? (int) $request->customer_client_id : null,
            'workspace_id'       => getActiveWorkSpace(),
            'sort_order'         => $request->filled('sort_order') ? (int) $request->sort_order : 100,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', __('Sender map created successfully.'));
    }

    public function updateSenderMap(Request $request, TimesheetInboundSenderMap $senderMap)
    {
        $this->authorizeManage();

        $request->validate([
            'match_type'         => 'required|in:email,domain',
            'match_value'        => 'required|string|max:255',
            'mailbox_id'         => 'nullable|integer|exists:timesheet_inbound_mailboxes,id',
            'employee_user_id'   => 'nullable|integer|exists:users,id',
            'project_id'         => 'nullable|integer',
            'task_id'            => 'nullable|integer',
            'customer_id'        => 'nullable|integer',
            'customer_client_id' => 'nullable|integer',
            'sort_order'         => 'nullable|integer',
        ]);

        $senderMap->update([
            'mailbox_id'         => $request->filled('mailbox_id')         ? (int) $request->mailbox_id         : null,
            'match_type'         => $request->match_type,
            'match_value'        => strtolower(trim($request->match_value)),
            'employee_user_id'   => $request->filled('employee_user_id')   ? (int) $request->employee_user_id   : null,
            'project_id'         => $request->filled('project_id')         ? (int) $request->project_id         : null,
            'task_id'            => $request->filled('task_id')            ? (int) $request->task_id            : null,
            'customer_id'        => $request->filled('customer_id')        ? (int) $request->customer_id        : null,
            'customer_client_id' => $request->filled('customer_client_id') ? (int) $request->customer_client_id : null,
            'sort_order'         => $request->filled('sort_order') ? (int) $request->sort_order : 100,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', __('Sender map updated successfully.'));
    }

    public function destroySenderMap(TimesheetInboundSenderMap $senderMap)
    {
        $this->authorizeManage();
        $senderMap->delete();
        return redirect()->back()->with('success', __('Sender map deleted.'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeManage(): void
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            abort(403, __('Permission denied.'));
        }
    }
}
