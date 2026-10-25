<?php

namespace Workdo\Timesheet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Entities\TimesheetInboundSenderMap;
use App\Models\User;

class TimesheetInboundMailboxController extends Controller
{
    public function index()
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $mailboxes  = TimesheetInboundMailbox::orderBy('name')->get();
        $senderMaps = TimesheetInboundSenderMap::with('mailbox', 'employee')
            ->orderBy('sort_order')
            ->get();

        $employees = User::where('workspace_id', getActiveWorkSpace())
            ->where('created_by', creatorId())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $projects = [];
        if (module_is_active('Taskly')) {
            $projects = \Workdo\Taskly\Entities\Project::where('workspace', getActiveWorkSpace())
                ->where('created_by', creatorId())
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $tasks = [];
        if (module_is_active('Taskly')) {
            $tasks = \Workdo\Taskly\Entities\Task::whereHas('project', function ($q) {
                $q->where('workspace', getActiveWorkSpace())
                  ->where('created_by', creatorId());
            })->orderBy('title')->pluck('title', 'id');
        }

        $customers = [];
        if (\Schema::hasTable('customers')) {
            $customers = \Workdo\Account\Entities\Customer::where('workspace', getActiveWorkSpace())
                ->where('created_by', creatorId())
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $customerClients = [];
        if (\Schema::hasTable('customer_clients')) {
            $customerClients = \Workdo\Account\Entities\CustomerClient::orderBy('name')
                ->pluck('name', 'id');
        }

        return view('timesheet::timesheet_email.index', compact(
            'mailboxes',
            'senderMaps',
            'employees',
            'projects',
            'tasks',
            'customers',
            'customerClients'
        ));
    }

    // ── Mailboxes ─────────────────────────────────────────────────────────────

    public function storeMailbox(Request $request)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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
            'folder'           => $request->folder,
            'processed_folder' => $request->processed_folder,
            'is_active'        => $request->boolean('is_active'),
        ]);

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mailbox creado correctamente.'));
    }

    public function updateMailbox(Request $request, TimesheetInboundMailbox $mailbox)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

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
            'folder'           => $request->folder,
            'processed_folder' => $request->processed_folder,
            'is_active'        => $request->boolean('is_active'),
        ];

        if ($request->filled('password_plain')) {
            $data['password_enc'] = Crypt::encryptString((string) $request->password_plain);
        }

        $mailbox->update($data);

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mailbox actualizado correctamente.'));
    }

    public function destroyMailbox(TimesheetInboundMailbox $mailbox)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $mailbox->delete();

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mailbox eliminado.'));
    }

    // ── Sender Maps ───────────────────────────────────────────────────────────

    public function storeSenderMap(Request $request)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $request->validate([
            'mailbox_id'         => 'nullable|integer|exists:timesheet_inbound_mailboxes,id',
            'match_type'         => 'required|in:email,domain',
            'match_value'        => 'required|string|max:255',
            'employee_user_id'   => 'nullable|integer|exists:users,id',
            'project_id'         => 'nullable|integer',
            'task_id'            => 'nullable|integer',
            'customer_id'        => 'nullable|integer',
            'customer_client_id' => 'nullable|integer',
            'sort_order'         => 'nullable|integer',
        ]);

        TimesheetInboundSenderMap::create([
            'mailbox_id'         => $request->filled('mailbox_id') ? (int) $request->mailbox_id : null,
            'match_type'         => $request->match_type,
            'match_value'        => strtolower(trim($request->match_value)),
            'employee_user_id'   => $request->filled('employee_user_id') ? (int) $request->employee_user_id : null,
            'project_id'         => $request->filled('project_id')         ? (int) $request->project_id         : null,
            'task_id'            => $request->filled('task_id')            ? (int) $request->task_id            : null,
            'customer_id'        => $request->filled('customer_id')        ? (int) $request->customer_id        : null,
            'customer_client_id' => $request->filled('customer_client_id') ? (int) $request->customer_client_id : null,
            'workspace_id'       => getActiveWorkSpace(),
            'sort_order'         => $request->filled('sort_order') ? (int) $request->sort_order : 100,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mapa de remitente creado correctamente.'));
    }

    public function updateSenderMap(Request $request, TimesheetInboundSenderMap $senderMap)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $request->validate([
            'mailbox_id'         => 'nullable|integer|exists:timesheet_inbound_mailboxes,id',
            'match_type'         => 'required|in:email,domain',
            'match_value'        => 'required|string|max:255',
            'employee_user_id'   => 'nullable|integer|exists:users,id',
            'project_id'         => 'nullable|integer',
            'task_id'            => 'nullable|integer',
            'customer_id'        => 'nullable|integer',
            'customer_client_id' => 'nullable|integer',
            'sort_order'         => 'nullable|integer',
        ]);

        $senderMap->update([
            'mailbox_id'         => $request->filled('mailbox_id') ? (int) $request->mailbox_id : null,
            'match_type'         => $request->match_type,
            'match_value'        => strtolower(trim($request->match_value)),
            'employee_user_id'   => $request->filled('employee_user_id') ? (int) $request->employee_user_id : null,
            'project_id'         => $request->filled('project_id')         ? (int) $request->project_id         : null,
            'task_id'            => $request->filled('task_id')            ? (int) $request->task_id            : null,
            'customer_id'        => $request->filled('customer_id')        ? (int) $request->customer_id        : null,
            'customer_client_id' => $request->filled('customer_client_id') ? (int) $request->customer_client_id : null,
            'sort_order'         => $request->filled('sort_order') ? (int) $request->sort_order : 100,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mapa de remitente actualizado.'));
    }

    public function destroySenderMap(TimesheetInboundSenderMap $senderMap)
    {
        if (!\Auth::user()->isAbleTo('timesheet manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $senderMap->delete();

        return redirect()->route('timesheet.email.index')
            ->with('success', __('Mapa de remitente eliminado.'));
    }
}
