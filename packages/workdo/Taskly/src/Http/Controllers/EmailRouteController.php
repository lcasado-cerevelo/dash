<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\TicketInboundMailbox;
use Workdo\Taskly\Entities\TicketInboundRoute;

class EmailRouteController extends Controller
{
    public function index()
    {
        $routes = TicketInboundRoute::with(['mailbox', 'project'])->orderBy('id', 'desc')->get();
        $mailboxes = TicketInboundMailbox::orderBy('name')->get();
        $projects = Project::orderBy('name')->get();

        return view('taskly::email_routes.index', compact('routes', 'mailboxes', 'projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'mailbox_id' => 'required|integer|exists:ticket_inbound_mailboxes,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'to_email'   => 'nullable|email',
            'to_contains' => 'nullable|string',
            'from_contains' => 'nullable|string',
            'subject_contains' => 'nullable|string',
        ]);

        TicketInboundRoute::create([
            'mailbox_id'       => $request->mailbox_id,
            'project_id'       => $request->project_id,
            'to_email'         => $request->to_email ? strtolower($request->to_email) : null,
            'to_contains'      => $request->to_contains,
            'from_contains'    => $request->from_contains,
            'subject_contains' => $request->subject_contains,
            'allow_new_ticket' => $request->has('allow_new_ticket'),
            'is_active'        => $request->has('is_active'),
            'sort_order'       => 100,
        ]);

        return redirect()
            ->route('taskly.email_routes.index')
            ->with('success', 'Ruta creada correctamente.');
    }

    public function destroy(TicketInboundRoute $route)
    {
        $route->delete();

        return redirect()
            ->route('taskly.email_routes.index')
            ->with('success', 'Ruta eliminada.');
    }
}