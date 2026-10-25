<?php

namespace App\Http\Controllers;

use App\DataTables\SupportDataTable;
use App\Models\EmailTemplate;
use App\Models\HelpdeskConversion;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\Task;
use Workdo\Account\Entities\Customer;
use Workdo\Account\Entities\CustomerClient;
use App\Models\CustomerClientAssigned;
use App\Models\HelpdeskTicketAudit;
use App\Services\HelpdeskTicketService;
use Illuminate\Support\Arr;


class HelpdeskTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(SupportDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('helpdesk ticket manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $user = Auth::user();
    
        $isSuper    = \Laratrust::hasRole('super admin') || \Laratrust::hasRole('superadministrator');
        $isCompany  = isset($user->type) && strtolower($user->type)==='company';
        $isClient   = \Laratrust::hasRole('Client');
        $isCustomer = \Laratrust::hasRole('Customer');
        $isHelpdesk = \Laratrust::hasRole('helpdesk') || \Laratrust::hasPermission('helpdesk ticket manage');
        $isConsult  = \Laratrust::hasRole('consultant') || \Laratrust::hasPermission('timesheet manage');
    
        $showEmpFilt = $isSuper || $isHelpdesk || $isConsult;
    
        // ===== Build set de tickets visibles (misma lógica que en query(), sin filtros de UI) =====
        $visible = HelpdeskTicket::query();
    
        if ($isSuper) {
            // sin restricciones
        } elseif ($isCompany) {
            if (function_exists('getActiveWorkSpace')) {
                $ws = getActiveWorkSpace();
                if ($ws) $visible->where('workspace', $ws);
            }
        } elseif ($isClient) {
            $visible->where(function($w) use ($user) {
                $w->whereExists(function($sq) use ($user) {
                    $sq->from('customers as c')
                       ->whereColumn('c.id','helpdesk_tickets.customer_id')
                       ->where('c.user_id', $user->id);
                })
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('customer_clients as cc')
                       ->join('customers as c','c.id','=','cc.customer_id')
                       ->whereColumn('cc.id','helpdesk_tickets.customer_client_id')
                       ->where('c.user_id', $user->id);
                })
                ->orWhere('helpdesk_tickets.user_id', $user->id)
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                       ->whereColumn('t.id','helpdesk_tickets.task_id')
                       ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id]);
                })
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t2')
                       ->join('user_projects as up','up.project_id','=','t2.project_id')
                       ->whereColumn('t2.id','helpdesk_tickets.task_id')
                       ->where('up.user_id', $user->id);
                });
            });
        } elseif ($isCustomer) {
            $visible->where(function($w) use ($user) {
                $w->whereExists(function($sq) use ($user) {
                    $sq->from('customer_clients_users as ccu')
                       ->whereColumn('ccu.customer_client_id','helpdesk_tickets.customer_client_id')
                       ->where('ccu.user_id', $user->id);
                })
                ->orWhere('helpdesk_tickets.user_id', $user->id)
                ->orWhere('helpdesk_tickets.created_by', $user->id)
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                       ->whereColumn('t.id','helpdesk_tickets.task_id')
                       ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id]);
                })
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t2')
                       ->join('user_projects as up','up.project_id','=','t2.project_id')
                       ->whereColumn('t2.id','helpdesk_tickets.task_id')
                       ->where('up.user_id', $user->id);
                });
            });
        } else {
            // demás roles
            $visible->where(function($w) use ($user) {
                $w->where('helpdesk_tickets.user_id', $user->id)
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                       ->whereColumn('t.id','helpdesk_tickets.task_id')
                       ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id]);
                })
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('tasks as t2')
                       ->join('user_projects as up','up.project_id','=','t2.project_id')
                       ->whereColumn('t2.id','helpdesk_tickets.task_id')
                       ->where('up.user_id', $user->id);
                })
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('customer_clients_users as ccu')
                       ->join('customer_clients as cc','cc.id','=','ccu.customer_client_id')
                       ->whereColumn('cc.customer_id','helpdesk_tickets.customer_id')
                       ->where('ccu.user_id', $user->id);
                })
                ->orWhere('helpdesk_tickets.created_by', $user->id);
            });
        }
    
        // ==== Poblar combo con los "Assigned To" reales del set visible ====
        $assignees = collect();
        if ($showEmpFilt) {
            $assignees = \DB::table('users')
                ->join('helpdesk_tickets as ht','ht.user_id','=','users.id')
                ->joinSub($visible->select('id'), 'vt', function($j){
                    $j->on('vt.id','=','ht.id');
                })
                ->whereNotNull('ht.user_id')
                ->distinct()
                ->orderBy('users.name')
                ->get(['users.id','users.name']);
        }
    
        $categories = \App\Models\HelpdeskTicketCategory::select('id','name')->orderBy('name')->get();
    
        return $dataTable->render('helpdesk_ticket.index', compact('assignees','categories','showEmpFilt'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('helpdesk ticket create')) {
            $categories = HelpdeskTicketCategory::get();
            $users = [];
            if(Auth::user()->type =='super admin')
            {
                $users = User::where('type', 'company')->get()->pluck('name', 'id');
            }
            $taskProjects = $this->getTaskProjectsForUser(Auth::user());
            
            $customers = $this->getCustomersForUser(Auth::user());
            $customerClients = collect();
            if ($cid = old('customer_id')) {
                $customerClients = $this->getCustomerClientsForUser(Auth::user(), $cid);
            }
            $canAssign       = $this->canAssignUsers();
            $assignableUsers = $canAssign ? $this->getAssignableUsers() : collect();
            $selectedAssigneeId = old('assigned_user_id');
            $parentTickets = $this->getAvailableParentTickets(
                null,
                old('task_id') ? (int) old('task_id') : null,
                old('customer_id') ? (int) old('customer_id') : null,
                old('customer_client_id') ? (int) old('customer_client_id') : null
            );            
            return view('helpdesk_ticket.create', compact(
                'categories','users','taskProjects','customers','customerClients',
                'canAssign','assignableUsers','selectedAssigneeId','parentTickets'
            ));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('helpdesk ticket create')) {
            if(Auth::user()->type =='super admin')
            {
                $validation = [
                    'name' => 'required',
                    'email' => 'required|string|email|max:255',
                    'category' => 'required|string|max:255',
                    'subject' => 'required|string|max:255',
                    'status' => 'required|string|max:100',
                    'task_id' => 'nullable|integer|exists:tasks,id',
                    'customer_id' => 'nullable|integer|exists:customers,id',
                    'customer_client_id' => 'nullable|integer|exists:customer_clients,id',
                    'priority' => 'required|integer|min:1|max:5',
                    'assigned_user_id' => 'nullable|integer|exists:users,id',
                    'parent_ticket_id' => 'nullable|integer|exists:helpdesk_tickets,id',
                ];
                $validator = \Validator::make(
                    $request->all(),
                    $validation
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    return redirect()->back()->withInput()->with('error', $messages->first());
                }

                $user = User::find($request->name);
            }
            else
            {
                $validation = [
                    'category' => 'required|string|max:255',
                    'subject' => 'required|string|max:255',
                    'status' => 'required|string|max:100',
                    'task_id' => 'nullable|integer|exists:tasks,id',
                    'customer_id' => 'nullable|integer|exists:customers,id',
                    'customer_client_id' => 'nullable|integer|exists:customer_clients,id',
                    'priority' => 'required|integer|min:1|max:5',
                    'assigned_user_id' => 'nullable|integer|exists:users,id',
                    'parent_ticket_id' => 'nullable|integer|exists:helpdesk_tickets,id',
                ];
                $validator = \Validator::make(
                    $request->all(),
                    $validation
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();
                    return redirect()->back()->withInput()->with('error', $messages->first());
                }

                $user = Auth::user();
            }

            $assigneeId = $this->resolveAssignedUserForManualTicket($request);
            $visibleToUserIds = $this->normalizeVisibleUsersFromRequest($request);
            $customerId = $request->customer_id;

            $ticket                  = new HelpdeskTicket();
            $ticket->ticket_id       = time() ;
            $data = [];
            if ($request->hasfile('attachments')) {
                foreach ($request->file('attachments') as $file) {

                    $name = $file->getClientOriginalName();
                    $data[] = [
                        'name' => $name,
                        'path' => 'uploads/helpdesk/' . $ticket->ticket_id . '/' . $name,
                    ];
                    multi_upload_file($file, 'attachments', $name, 'helpdesk/' . $ticket->ticket_id);
                }
            }
            $ticket->name           = !empty($user) ? $user->name : '';
            $ticket->email          = $user->email;
            $ticket->attachments    = json_encode($data) ;
            $ticket->category       = $request->category ;
            $ticket->status = $request->input('status', $ticket->status);
            $ticket->subject        = $request->subject ;
            $ticket->task_id        = $request->task_id ;
            $ticket->customer_id    = $customerId ;
            $ticket->customer_client_id = $request->customer_client_id ;
            $ticket->parent_ticket_id = $request->filled('parent_ticket_id')
                ? (int) $request->parent_ticket_id
                : null;            
            $ticket->description    = !empty($request->description) ? $request->description : ''  ;
            $ticket->priority       = (int) $request->input('priority', 3);
            $ticket->user_id        = $assigneeId ?: $user->id;
            $ticket->created_by     = creatorId() ;
            if(Auth::user()->type == 'super admin'){

                $ticket->workspace      = getActiveWorkSpace($user->id);
            }else{
                $ticket->workspace      = getActiveWorkSpace();
            }
            if ($ticket->customer_client_id) {
                $belongs = CustomerClient::where('id', $ticket->customer_client_id)
                           ->where('customer_id', $customerId)->exists();
                if (!$belongs) {
                    $ticket->customer_client_id = null;
                }
            }            
            
            $creatorUserId = (int) creatorId();
            $ticket->user_id = $assigneeId ?: $ticket->user_id;
            $ticket->visible_to_user_ids = HelpdeskTicketService::encodeIds($visibleToUserIds);
            $ticket->touched_by_user_ids = HelpdeskTicketService::encodeIds([$creatorUserId]);

            $ticket->save();
            
            HelpdeskTicketService::addTouchedUser($ticket, $creatorUserId);
            if (!empty($ticket->user_id)) {
                $this->sendAssignedTicketEmail($ticket);
            }
            $user = User::where('id', $ticket->created_by)->first();
            $ticket_url = route('helpdesk.view', [Crypt::encrypt($ticket->ticket_id)]);
            if(!empty(admin_setting('New Helpdesk Ticket')) && admin_setting('New Helpdesk Ticket')  == true)
            {
                $uArr = [
                    'ticket_name' => $ticket->name,
                    'email' => $ticket->email,
                    'ticket_id' => $ticket->ticket_id,
                    'ticket_url' => $ticket_url,
                    // Datos del ticket para que el correo de creacion sea informativo
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $this->priorityLabel($ticket->priority, 'es'),
                    'created_user_name' => optional($user)->name,
                    'company_name' => company_setting('company_name',$ticket->user_id)
                ];

                try
                {
                    if(Auth::user()->type == 'super admin')
                    {
                        $resp = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket', [$ticket->email], $uArr);
                    }else
                    {
                        if($user->type != 'super admin'){
                            $user        = User::where('type', 'super admin')->first();
                        }
                        $resp = EmailTemplate::sendEmailTemplate('New Helpdesk Ticket', [$user->email], $uArr);
                    }
                }
                catch(\Exception $e)
                {
                    $resp['error'] = $e->getMessage();
                }
                return redirect()->route('helpdesk.index')->with('success', __('The ticket has been created successfully') . ((isset($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
            }
            return redirect()->route('helpdesk.index')->with('success', __('The ticket has been created successfully'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\HelpdeskTicket  $helpdeskTicket
     * @return \Illuminate\Http\Response
     */
    public function show(HelpdeskTicket $helpdeskTicket, $ticket_id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', __('Please log in first.'));
        }        
        $ticket_id = Crypt::decrypt($ticket_id);
        $ticket = $this->getTicketScopeQuery()->where('ticket_id', '=', $ticket_id)->first();
    
        if (!$ticket) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $ticket->load(['task.project','customer','customerClient','tcategory','user','parentTicket','childTickets']);

        $timesheets = \Workdo\Timesheet\Entities\Timesheet::with('user')
            ->where('ticket_id', $ticket->id)
            ->orderByDesc('date')
            ->get();
    
        $canHeaderEdit = $this->canEditHeaderActions($ticket);
        $canPriorityCategoryEdit = $this->canChangeCategoryAndPriority();
        $canReply = $this->canReplyTicket($ticket);
        $canViewTimesheets = $this->canViewTimesheetsSection();
    
        return view('helpdesk_ticket.show', compact('ticket', 'timesheets', 'canHeaderEdit', 'canPriorityCategoryEdit', 'canReply', 'canViewTimesheets'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\HelpdeskTicket  $helpdeskTicket
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = \Auth::user();
        if (Auth::user()->isAbleTo('helpdesk ticket show')) {
            $ticket = HelpdeskTicket::find($id);
            if ($ticket) {
                $categories = HelpdeskTicketCategory::get();
                $users = [];
                if(Auth::user()->type =='super admin')
                {
                    $users = User::where('type', 'company')->get()->pluck('name', 'id');
                }
                $taskProjects = $this->getTaskProjectsForUser(Auth::user());
                $customers = $this->getCustomersForUser(Auth::user());  
                $customerClients = collect();
                if (!empty($ticket->customer_id)) {
                    $customerClients = $this->getCustomerClientsForUser(Auth::user(), $ticket->customer_id);
                }
                $ticket = HelpdeskTicket::with(['task.project','customer','customerClient','tcategory'])->find($id);
                $canAssign       = $this->canAssignUsers();
                $assignableUsers = $canAssign ? $this->getAssignableUsers() : collect();
                $selectedAssigneeId = old('assigned_user_id', $ticket->user_id);

                $ticketVisibleIds = HelpdeskTicketService::normalizeIds($ticket->visible_to_user_ids);
                $canHeaderEdit = $this->canEditHeaderActions($ticket);
                $canPriorityCategoryEdit = $this->canChangeCategoryAndPriority();
                $canReply = $this->canReplyTicket($ticket);
                $assignableUsersForTicket = $this->getAssignableUsersForTicket($ticket)
                    ->map(function ($u) {
                        if (!isset($u->email)) {
                            $email = DB::table('users')->where('id', $u->id)->value('email');
                            $u->email = $email;
                        }
                        return $u;
                    });
                $canDeleteTicketAttachment =
                    (
                        \Laratrust::hasRole('Consultant')
                        && (int) $ticket->user_id === (int) Auth::id()
                    )
                    ||
                    $this->isCompanyLike(Auth::user());
                $canViewTimesheets = $this->canViewTimesheetsSection();
                $parentTickets = $this->getAvailableParentTickets(
                    $ticket->id,
                    old('task_id', $ticket->task_id) ? (int) old('task_id', $ticket->task_id) : null,
                    old('customer_id', $ticket->customer_id) ? (int) old('customer_id', $ticket->customer_id) : null,
                    old('customer_client_id', $ticket->customer_client_id) ? (int) old('customer_client_id', $ticket->customer_client_id) : null
                );               
                return view('helpdesk_ticket.edit', compact(
                    'ticket','categories','users','taskProjects','customers','customerClients',
                    'canAssign','assignableUsers','selectedAssigneeId',
                    'ticketVisibleIds','canHeaderEdit','canPriorityCategoryEdit','canReply', 'canViewTimesheets','assignableUsersForTicket','canDeleteTicketAttachment','parentTickets'
                ));
            } else {
                return view('403');
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\HelpdeskTicket  $helpdeskTicket
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('helpdesk ticket edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $ticket = HelpdeskTicket::find($id);
    
        $isCompanyLike = $this->isCompanyLike(Auth::user());
        $isNewTicket = strtolower(trim((string) $ticket->status)) === 'new';    
    
        $oldAssignedUserId = $ticket->user_id;
        $oldStatus = $ticket->status;
        $oldPriority = $ticket->priority;
        $oldCategory = $ticket->category;
        $oldCreatedBy = $ticket->created_by;
        $oldEmail = $ticket->email;        
        $visibleToUserIds = $this->normalizeVisibleUsersFromRequest($request);
    
        if (Auth::user()->type == 'super admin') {
            $validation = [
                'name' => 'required',
                'email' => 'required|string|email|max:255',
                'category' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'status' => 'required|string|max:100',
                'task_id' => 'nullable|integer|exists:tasks,id',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'customer_client_id' => 'nullable|integer|exists:customer_clients,id',
                'priority' => 'required|integer|min:1|max:5',
                'assigned_user_id' => 'nullable|integer|exists:users,id',
            ];
        } else {
            $validation = [
                'category' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'status' => 'required|string|max:100',
                'task_id' => 'nullable|integer|exists:tasks,id',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'customer_client_id' => 'nullable|integer|exists:customer_clients,id',
                'priority' => 'required|integer|min:1|max:5',
                'assigned_user_id' => 'nullable|integer|exists:users,id',
            ];
            
            if ($isCompanyLike && $isNewTicket) {
                $validation['email'] = 'required|string|email|max:255';
            }
        }
    
        $validator = \Validator::make($request->all(), $validation);
    
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->withInput()->with('error', $messages->first());
        }
    
        if ($request->hasfile('attachments')) {
            $data = [];
            foreach ($request->file('attachments') as $file) {
                $name = $file->getClientOriginalName();
                $data[] = [
                    'name' => $name,
                    'path' => 'uploads/helpdesk/' . $ticket->ticket_id . '/' . $name,
                ];
                multi_upload_file($file, 'attachments', $name, 'helpdesk/' . $ticket->ticket_id);
            }
    
            $json_decode = !empty($ticket->attachments) ? json_decode($ticket->attachments, true) : [];
            $attachments = json_encode(array_merge($json_decode ?: [], $data));
            $ticket->attachments = $attachments;
        }

        if ($request->filled('created_by_user_id')) {
            $ticket->created_by = (int) $request->created_by_user_id;
        }
        
        $customerId = $request->customer_id;
    
        $isCompanyLike = $this->isCompanyLike(Auth::user());
        $isNewTicket = strtolower(trim((string) $ticket->status)) === 'new';
        
        // Mantener name siempre, pero permitir email editable para company-like en tickets New
        if ($isCompanyLike && $isNewTicket) {
            $ticket->email = trim((string) $request->input('email', $ticket->email));
        }
        
        // Mantener name y email originales del ticket
        $ticket->category = !empty($request->category) ? $request->category : '';
        $ticket->subject = !empty($request->subject) ? $request->subject : '';
        $ticket->status = $request->input('status', $ticket->status);
        $ticket->task_id = $request->task_id;
        $ticket->customer_id = $customerId;
        $ticket->customer_client_id = $request->customer_client_id;
        $ticket->description = !empty($request->description) ? $request->description : '';
        $ticket->priority = (int) $request->input('priority', $ticket->priority ?? 3);
        $ticket->parent_ticket_id = $request->filled('parent_ticket_id')
            ? (int) $request->parent_ticket_id
            : null;
            
        $newAssignedUserId = $this->resolveAssignedUserForManualTicket($request);
        if (!empty($newAssignedUserId)) {
            $ticket->user_id = $newAssignedUserId;
        } elseif (empty($ticket->user_id)) {
            $ticket->user_id = Auth::id();
        }
    
        $ticket->workspace = getActiveWorkSpace();
    
        if ($ticket->customer_client_id) {
            $belongs = CustomerClient::where('id', $ticket->customer_client_id)
                ->where('customer_id', $customerId)
                ->exists();
    
            if (!$belongs) {
                $ticket->customer_client_id = null;
            }
        }
    
        $ticket->visible_to_user_ids = HelpdeskTicketService::encodeIds($visibleToUserIds);
        
        if ((int) $ticket->id === (int) $ticket->parent_ticket_id) {
            return redirect()->back()->withInput()->with('error', __('A ticket cannot be its own parent.'));
        }        
        
        $ticket->save();

        HelpdeskTicketService::audit($ticket, 'created_by', $oldCreatedBy, $ticket->created_by, Auth::id());
        HelpdeskTicketService::audit($ticket, 'email', $oldEmail, $ticket->email, Auth::id());    
        HelpdeskTicketService::audit($ticket, 'user_id', $oldAssignedUserId, $ticket->user_id, Auth::id());
        HelpdeskTicketService::audit($ticket, 'status', $oldStatus, $ticket->status, Auth::id());
        HelpdeskTicketService::audit($ticket, 'priority', $oldPriority, $ticket->priority, Auth::id());
        HelpdeskTicketService::audit($ticket, 'category', $oldCategory, $ticket->category, Auth::id());
    
        HelpdeskTicketService::addTouchedUser($ticket, Auth::id());
    
        if ($oldStatus !== 'Closed' && $ticket->status === 'Closed') {
            HelpdeskTicketService::createSystemReply(
                $ticket,
                'El ticket fue cerrado por ' . Auth::user()->name . '.',
                Auth::id()
            );
        }
    
        if ((int)$oldAssignedUserId !== (int)$ticket->user_id && !empty($ticket->user_id)) {
            $this->sendAssignedTicketEmail($ticket);
        }
    
        return redirect()->back()->with('success', __('The ticket details are updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HelpdeskTicket  $helpdeskTicket
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (Auth::user()->isAbleTo('helpdesk ticket delete')) {
            $ticket = HelpdeskTicket::find($id);
            $conversions = HelpdeskConversion::where('ticket_id', $ticket->id)->get();
            if (count($conversions) > 0) {
                $conversions = HelpdeskConversion::where('ticket_id', $ticket->id)->delete();
            }
            delete_folder('helpdesk/' . $ticket->ticket_id);
            $ticket->delete();
            return redirect()->back()->with('success', __('The ticket has been deleted'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function  getUser(Request $request)
    {
        $user = User::find($request->user_id);
        if ($user) {
            $userData = [
                'name' => $user->name,
                'email' => $user->email,
            ];
            return response()->json($userData);
        } else {
            return response()->json(['error' => 'User not found']);
        }

    }

    public function attachmentDestroy($ticket_id, $id)
    {
        $ticket = HelpdeskTicket::find($ticket_id);
    
        if (!$ticket) {
            return redirect()->back()->with('error', __('Ticket not found.'));
        }
    
        $auth = Auth::user();
    
        $canDeleteTicketAttachment =
            (
                \Laratrust::hasRole('Consultant')
                && (int) $ticket->user_id === (int) Auth::id()
            )
            ||
            $this->isCompanyLike(Auth::user());
            
        if (!$canDeleteTicketAttachment) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $attachments = !empty($ticket->attachments)
            ? json_decode($ticket->attachments)
            : [];
    
        if (!isset($attachments[$id])) {
            return redirect()->back()->with('error', __('Attachment is missing'));
        }
    
        $deletedAttachmentName = $attachments[$id]->name ?? 'Attachment';
        $deletedAttachmentPath = $attachments[$id]->path ?? null;
    
        if (!empty($deletedAttachmentPath)) {
            delete_file($deletedAttachmentPath);
        }
    
        unset($attachments[$id]);
    
        $ticket->attachments = json_encode(array_values($attachments));
        $ticket->save();
    
        HelpdeskTicketService::audit(
            $ticket,
            'attachments',
            $deletedAttachmentName,
            null,
            Auth::id()
        );
    
        /*HelpdeskTicketService::createSystemReply(
            $ticket,
            'Attachment deleted: ' . $deletedAttachmentName,
            Auth::id()
        );*/
    
        HelpdeskTicketService::addTouchedUser($ticket, Auth::id());
    
        return redirect()->back()->with('success', __('Attachment has been deleted'));
    }
    public function storeNote($ticketID, Request $request)
    {

        $ticket = HelpdeskTicket::find($ticketID);
        if ($ticket) {
            $ticket->note = $request->note;
            $ticket->save();

            HelpdeskTicketService::addTouchedUser($ticket, Auth::id());

            return redirect()->back()->with('success', __('Ticket note saved successfully'));
        } else {
            return view('403');
        }
    }

    public function reply($ticket_id, Request $request)
    {

        $ticket = HelpdeskTicket::where('ticket_id', '=', $ticket_id)->first();
        
        if (!$ticket) {
            return redirect()->back()->with('error', __('Ticket not found.'));
        }
        
        if (!$this->canReplyTicket($ticket)) {
            return redirect()->back()->with('error', __('Only the assigned user can reply to this ticket.'));
        }

        if ($ticket) {
            $validation = [
                'reply_description' => 'required'
            ];
            $validator = \Validator::make(
                $request->all(),
                $validation
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->withInput()->with('error', $messages->first());
            }

            $post                = [];
            $post['sender']      = 'user';
            $post['ticket_id']   = $ticket->id;
            $post['description'] = $request->reply_description;
            $data                = [];
            if ($request->hasfile('reply_attachments')) {
                foreach ($request->file('reply_attachments') as $file) {

                    $name = $file->getClientOriginalName();
                    $data[] = [
                        'name' => $name,
                        'path' => 'uploads/helpdesk/' . $ticket->ticket_id . '/' . $name
                    ];
                    $path = multi_upload_file($file, 'attachments', $name, 'helpdesk/' . $ticket->ticket_id);
                    if($path['flag'] == '0')
                    {
                         return redirect()->back()->with('error',$path['msg']);
                    }
                }
            }
            $post['attachments'] = json_encode($data);
            HelpdeskConversion::create($post);

            HelpdeskTicketService::addTouchedUser($ticket, Auth::id());

            // Send Email to User
            try {
                if (!empty(admin_setting('New Helpdesk Ticket Reply')) && admin_setting('New Helpdesk Ticket Reply') == true) {
                    $this->sendReplyNotificationToTicketEmail($ticket, $request->reply_description);
            
                    // opcional: si quieres seguir notificando también al creador interno
                    // $this->sendReplyNotificationToTicketCreator($ticket, $request->reply_description);
                }
            } catch (\Exception $e) {
                $resp['error'] = $e->getMessage();
            }
            return redirect()->back()->with('success', __('Reply added successfully.') . ((isset($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));

        } else {
            return redirect()->back()->with('error', __('Something is wrong'));
        }
    }
    
    public function reassign($id, Request $request)
    {
        $ticket = HelpdeskTicket::findOrFail($id);
    
        if (!$this->canEditHeaderActions($ticket)) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $validator = \Validator::make($request->all(), [
            'assigned_user_id' => 'required|integer|exists:users,id',
            'comment' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }
    
        $oldAssigned = $ticket->user_id;
        $ticket->user_id = (int) $request->assigned_user_id;
        $ticket->save();
    
        HelpdeskTicketService::audit($ticket, 'user_id', $oldAssigned, $ticket->user_id, Auth::id());
        HelpdeskTicketService::addTouchedUser($ticket, Auth::id());
        HelpdeskTicketService::createSystemReply(
            $ticket,
            trim($request->comment),
            Auth::id()
        );
    
        $this->sendAssignedTicketEmail($ticket, $request->comment);
    
        return redirect()->back()->with('success', __('Ticket reassigned successfully.'));
    }

    public function changeStatus($id, Request $request)
    {
        $ticket = HelpdeskTicket::findOrFail($id);
    
        if (!$this->canEditHeaderActions($ticket)) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $validator = \Validator::make($request->all(), [
            'status' => 'required|in:New,In Progress,On Hold,Validation,Closed,Canceled',
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }
    
        $oldStatus = $ticket->status;
        $ticket->status = $request->status;
        $ticket->save();
    
        HelpdeskTicketService::audit($ticket, 'status', $oldStatus, $ticket->status, Auth::id());
    
        if ($oldStatus !== 'Closed' && $ticket->status === 'Closed') {
            HelpdeskTicketService::createSystemReply(
                $ticket,
                'Ticket was closed by ' . Auth::user()->name . '.',
                Auth::id()
            );
        }
    
        if ($oldStatus !== 'Canceled' && $ticket->status === 'Canceled') {
            HelpdeskTicketService::createSystemReply(
                $ticket,
                'Ticket was canceled by  ' . Auth::user()->name . '.',
                Auth::id()
            );
        }
        
        return redirect()->back()->with('success', __('Ticket status updated successfully.'));
    }

    public function changePriority($id, Request $request)
    {
        $ticket = HelpdeskTicket::findOrFail($id);
    
        if (!$this->canChangeCategoryAndPriority()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $validator = \Validator::make($request->all(), [
            'priority' => 'required|integer|min:1|max:5',
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }
    
        $oldPriority = $ticket->priority;
        $ticket->priority = (int) $request->priority;
        $ticket->save();
    
        HelpdeskTicketService::audit($ticket, 'priority', $oldPriority, $ticket->priority, Auth::id());
    
        return redirect()->back()->with('success', __('Ticket priority updated successfully.'));
    }
    
    public function changeCategory($id, Request $request)
    {
        $ticket = HelpdeskTicket::findOrFail($id);
    
        if (!$this->canChangeCategoryAndPriority()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    
        $validator = \Validator::make($request->all(), [
            'category' => 'required|integer|exists:helpdesk_ticket_categories,id',
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }
    
        $oldCategory = $ticket->category;
        $ticket->category = (int) $request->category;
        $ticket->save();
    
        HelpdeskTicketService::audit($ticket, 'category', $oldCategory, $ticket->category, Auth::id());
    
        return redirect()->back()->with('success', __('Ticket category updated successfully.'));
    }

    private function getTaskProjectsForUser(User $user)
    {
        // Roles / permisos
        $isSuper    = \Laratrust::hasRole('super admin') || \Laratrust::hasRole('superadministrator') || (isset($user->type) && strtolower($user->type)==='super admin');
        $isCompany  = isset($user->type) && strtolower($user->type)==='company';
        $isClient   = \Laratrust::hasRole('Client');
        $isCustomer = \Laratrust::hasRole('Customer');
    
        // SELECT base con joins a Project/Client/Customer
        $q = DB::table('tasks')
            ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
            ->leftJoin('client_projects as cp', 'cp.project_id', '=', 'projects.id')
            ->leftJoin('customers', 'customers.user_id', '=', 'cp.client_id')
            ->select([
                'tasks.id as id',
                'tasks.title as title',
                DB::raw('projects.id as project_id'),
                DB::raw('projects.name as project_name'),
                DB::raw('customers.id as customer_id'),
            ]);
    
        if ($isSuper) {
            // sin restricciones
        } elseif ($isCompany) {
            // Todo dentro del workspace activo
            if (function_exists('getActiveWorkSpace')) {
                $ws = getActiveWorkSpace();
                // en la mayoría de instalaciones el workspace está en projects; si también lo tienes en tasks, agrega un OR.
                $q->where('projects.workspace', $ws);
            }
        } elseif ($isClient) {
            // Proyectos/Tareas del cliente (customers.user_id = id del usuario "Client")
            $q->where('customers.user_id', $user->id);
        } elseif ($isCustomer) {
            // 1) Asignado por CSV en tasks.assign_to
            // 2) Asociado por user_projects al project
            // 3) Asociado a customer_clients del cliente (ccu -> cc -> customers -> cp.client_id)
            $q->where(function($w) use ($user) {
                $w->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tasks.assign_to,''), ' ', '')) > 0", [$user->id])
                  ->orWhereExists(function($sq) use ($user) {
                      $sq->from('user_projects as up')
                         ->whereColumn('up.project_id', 'tasks.project_id')
                         ->where('up.user_id', $user->id);
                  });
            });
        } else {
            // Demás roles: mismo alcance "mixto" (asignado a task, asociado a project, o asociado a customers del cliente)
            $q->where(function($w) use ($user) {
                $w->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tasks.assign_to,''), ' ', '')) > 0", [$user->id])
                  ->orWhereExists(function($sq) use ($user) {
                      $sq->from('user_projects as up')
                         ->whereColumn('up.project_id', 'tasks.project_id')
                         ->where('up.user_id', $user->id);
                  })
                  ->orWhereExists(function($sq) use ($user) {
                      $sq->from('customer_clients_users as ccu')
                         ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
                         ->join('customers as c', 'c.id', '=', 'cc.customer_id')
                         ->where('ccu.user_id', $user->id)
                         ->whereColumn('c.user_id', 'cp.client_id');
                  });
            });
        }
    
        // Distintos, ordenados y mapeados al formato del blade
        $rows = $q->distinct()
            ->orderByDesc('tasks.id')
            ->get();
    
        return $rows->map(function ($t) {
            return [
                'id'          => $t->id,
                'label'       => $t->title.' / '.($t->project_name ?: 'Without project'),
                'project_id'  => $t->project_id,
                'customer_id' => $t->customer_id,
            ];
        })->values();
    }
    
    private function getCustomersForUser(User $user)
    {
        $isSuper    = \Laratrust::hasRole('super admin') || \Laratrust::hasRole('superadministrator') || (isset($user->type) && strtolower($user->type)==='super admin');
        $isCompany  = isset($user->type) && strtolower($user->type)==='company';
        $isClient   = \Laratrust::hasRole('Client');
        $isCustomer = \Laratrust::hasRole('Customer');
    
        $q = DB::table('customers')->select('customers.id','customers.name');
    
        if ($isSuper) {
            // sin restricciones
        } elseif ($isCompany) {
            // Todos los Customers dentro del workspace activo
            if (function_exists('getActiveWorkSpace')) {
                $ws = getActiveWorkSpace();
                // Ajusta esta columna si tu "workspace" está en otra tabla/campo
                $q->where('customers.workspace', $ws);
            }
        } elseif ($isClient) {
            // Todos los Customers del cliente (el "dueño" es el user client actual)
            $q->where('customers.user_id', $user->id);
        } elseif ($isCustomer) {
            // Customers vinculados por:
            // 1) tareas asignadas al usuario -> proyecto -> client_projects -> customers (dueño del project)
            // 2) relación customer_clients_users -> customer_clients -> customers
            $q->where(function($w) use ($user) {
                // (1) por tareas asignadas al usuario
                $w->whereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                      ->join('projects as p','p.id','=','t.project_id')
                      ->join('client_projects as cp','cp.project_id','=','p.id')
                      ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id])
                      // cp.client_id = customers.user_id (dueño del proyecto)
                      ->whereColumn('cp.client_id','customers.user_id');
                })
                // (2) por relación a customer_clients
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('customer_clients_users as ccu')
                       ->join('customer_clients as cc','cc.id','=','ccu.customer_client_id')
                       ->where('ccu.user_id', $user->id)
                       ->whereColumn('cc.customer_id','customers.id');
                });
            });
        } else {
            // Resto de roles:
            // - asignación a tasks
            // - asociación a projects por user_projects
            // - asociación a customer_clients_users
            $q->where(function($w) use ($user) {
                // Por tareas asignadas
                $w->whereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                      ->join('projects as p','p.id','=','t.project_id')
                      ->join('client_projects as cp','cp.project_id','=','p.id')
                      ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id])
                      ->whereColumn('cp.client_id','customers.user_id');
                })
                // Por acceso a projects (user_projects)
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('user_projects as up')
                      ->join('projects as p','p.id','=','up.project_id')
                      ->join('client_projects as cp','cp.project_id','=','p.id')
                      ->where('up.user_id', $user->id)
                      ->whereColumn('cp.client_id','customers.user_id');
                })
                // Por relación a customer_clients
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('customer_clients_users as ccu')
                       ->join('customer_clients as cc','cc.id','=','ccu.customer_client_id')
                       ->where('ccu.user_id', $user->id)
                       ->whereColumn('cc.customer_id','customers.id');
                });
            });
        }
    
        return $q->distinct()->orderBy('customers.name')->get();
    } 
    
    private function getCustomerClientsForUser(User $user, $customerId = null)
    {
        $isSuper    = \Laratrust::hasRole('super admin') || \Laratrust::hasRole('superadministrator') || (isset($user->type) && strtolower($user->type)==='super admin');
        $isCompany  = isset($user->type) && strtolower($user->type)==='company';
        $isClient   = \Laratrust::hasRole('Client');
        $isCustomer = \Laratrust::hasRole('Customer');
    
        // Base: CC ← Customers
        $q = \DB::table('customer_clients as cc')
            ->join('customers as c','c.id','=','cc.customer_id')
            ->select('cc.id','cc.name','cc.customer_id');
    
        if (!empty($customerId)) {
            $q->where('cc.customer_id', (int)$customerId);
        }
    
        if ($isSuper) {
            // sin restricciones
        } elseif ($isCompany) {
            // Todos los CC dentro del workspace activo (ajusta el campo de workspace si está en otra tabla)
            if (function_exists('getActiveWorkSpace')) {
                $ws = getActiveWorkSpace();
                if ($ws) {
                    // Si tu workspace vive en customers, esto está bien:
                    $q->where('c.workspace', $ws);
                    // Si vive en otra tabla/campo, ajusta aquí.
                }
            }
        } elseif ($isClient) {
            // Todos los CC cuyos customers pertenecen al "client" actual
            // (customers.user_id es el dueño/cliente)
            $q->where('c.user_id', $user->id);
        } elseif ($isCustomer) {
            // CC asociados directamente al usuario vía customer_clients_users
            $q->join('customer_clients_users as ccu', 'ccu.customer_client_id', '=', 'cc.id')
              ->where('ccu.user_id', $user->id)
              // opcional pero recomendable: asegura que los CC sean de customers
              // “propiedad” del mismo client (consistente con tu modelo)
              ->where('c.user_id', $user->id);
        } else {
            // Resto de roles:
            // - por tareas asignadas -> proyecto -> client_projects -> customers → customer_clients
            // - por acceso a proyectos via user_projects → customers → customer_clients
            // - por relación directa customer_clients_users
            $q->where(function($w) use ($user) {
                // (1) tasks asignadas
                $w->whereExists(function($sq) use ($user) {
                    $sq->from('tasks as t')
                      ->join('projects as p','p.id','=','t.project_id')
                      ->join('client_projects as cp','cp.project_id','=','p.id')
                      ->join('customers as c2','c2.user_id','=','cp.client_id')
                      ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(t.assign_to,''), ' ', '')) > 0", [$user->id])
                      ->whereColumn('c2.id','cc.customer_id');
                })
                // (2) user_projects
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('user_projects as up')
                      ->join('projects as p','p.id','=','up.project_id')
                      ->join('client_projects as cp','cp.project_id','=','p.id')
                      ->join('customers as c3','c3.user_id','=','cp.client_id')
                      ->where('up.user_id', $user->id)
                      ->whereColumn('c3.id','cc.customer_id');
                })
                // (3) relación directa a CC
                ->orWhereExists(function($sq) use ($user) {
                    $sq->from('customer_clients_users as ccu')
                       ->whereColumn('ccu.customer_client_id','cc.id')
                       ->where('ccu.user_id', $user->id);
                });
            });
        }
    
        return $q->distinct()->orderBy('cc.name')->get();
    }    
    
    private function canAssignUsers(): bool
    {
        return true;
        return !\Laratrust::hasRole('Client') && !\Laratrust::hasRole('Customer');
    }
    
    private function getAssignableUsers()
    {
        $authUser = Auth::user();
    
        if (!$authUser) {
            return collect();
        }
    
        $userId = (int) $authUser->id;
        $ws = function_exists('getActiveWorkSpace') ? (int) getActiveWorkSpace() : null;
    
        if (empty($ws)) {
            return collect();
        }
    
        /*
        |--------------------------------------------------------------------------
        | 1) Customers del usuario logueado dentro del workspace activo
        |--------------------------------------------------------------------------
        | - customers.user_id
        | - customer_users.user_id
        | - customer_clients_users -> customer_clients.customer_id
        */
        $customerIds = collect();
    
        if (\Schema::hasTable('customers') && \Schema::hasColumn('customers', 'user_id')) {
            $customerIds = $customerIds->merge(
                DB::table('customers')
                    ->where('workspace', $ws)
                    ->where('user_id', $userId)
                    ->pluck('id')
            );
        }
    
        if (\Schema::hasTable('customer_users')) {
            $customerIds = $customerIds->merge(
                DB::table('customer_users as cu')
                    ->join('customers as c', 'c.id', '=', 'cu.customer_id')
                    ->where('c.workspace', $ws)
                    ->where('cu.user_id', $userId)
                    ->pluck('cu.customer_id')
            );
        }
    
        if (\Schema::hasTable('customer_clients_users') && \Schema::hasTable('customer_clients')) {
            $customerIds = $customerIds->merge(
                DB::table('customer_clients_users as ccu')
                    ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
                    ->join('customers as c', 'c.id', '=', 'cc.customer_id')
                    ->where('c.workspace', $ws)
                    ->where('ccu.user_id', $userId)
                    ->pluck('cc.customer_id')
            );
        }
    
        $customerIds = $customerIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    
        /*
        |--------------------------------------------------------------------------
        | 2) Customer Clients del usuario logueado dentro del workspace activo
        |--------------------------------------------------------------------------
        */
        $customerClientIds = collect();
    
        if (\Schema::hasTable('customer_clients_users') && \Schema::hasTable('customer_clients')) {
            $customerClientIds = DB::table('customer_clients_users as ccu')
                ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
                ->join('customers as c', 'c.id', '=', 'cc.customer_id')
                ->where('c.workspace', $ws)
                ->where('ccu.user_id', $userId)
                ->pluck('ccu.customer_client_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }
    
        /*
        |--------------------------------------------------------------------------
        | 3) Usuarios que comparten esos customers / customer_clients
        |    y que además pertenecen al workspace activo por customers.workspace
        |--------------------------------------------------------------------------
        */
        $q = DB::table('users')
            ->select('users.id', 'users.name', 'users.email')
            ->distinct();
    
        $q->where(function ($w) use ($customerIds, $customerClientIds, $ws) {
    
            if ($customerIds->isNotEmpty()) {
                // Dueños de esos customers en el mismo workspace
                $w->orWhereExists(function ($sq) use ($customerIds, $ws) {
                    $sq->from('customers as c')
                        ->whereColumn('c.user_id', 'users.id')
                        ->where('c.workspace', $ws)
                        ->whereIn('c.id', $customerIds->all());
                });
    
                // Usuarios vinculados por customer_users a esos customers en el mismo workspace
                if (\Schema::hasTable('customer_users')) {
                    $w->orWhereExists(function ($sq) use ($customerIds, $ws) {
                        $sq->from('customer_users as cu')
                            ->join('customers as c', 'c.id', '=', 'cu.customer_id')
                            ->whereColumn('cu.user_id', 'users.id')
                            ->where('c.workspace', $ws)
                            ->whereIn('cu.customer_id', $customerIds->all());
                    });
                }
    
                // Usuarios vinculados a customer_clients de esos customers en el mismo workspace
                if (\Schema::hasTable('customer_clients_users') && \Schema::hasTable('customer_clients')) {
                    $w->orWhereExists(function ($sq) use ($customerIds, $ws) {
                        $sq->from('customer_clients_users as ccu')
                            ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
                            ->join('customers as c', 'c.id', '=', 'cc.customer_id')
                            ->whereColumn('ccu.user_id', 'users.id')
                            ->where('c.workspace', $ws)
                            ->whereIn('cc.customer_id', $customerIds->all());
                    });
                }
            }
    
            if ($customerClientIds->isNotEmpty() && \Schema::hasTable('customer_clients_users') && \Schema::hasTable('customer_clients')) {
                // Usuarios que comparten exactamente esos customer_client dentro del mismo workspace
                $w->orWhereExists(function ($sq) use ($customerClientIds, $ws) {
                    $sq->from('customer_clients_users as ccu2')
                        ->join('customer_clients as cc2', 'cc2.id', '=', 'ccu2.customer_client_id')
                        ->join('customers as c2', 'c2.id', '=', 'cc2.customer_id')
                        ->whereColumn('ccu2.user_id', 'users.id')
                        ->where('c2.workspace', $ws)
                        ->whereIn('ccu2.customer_client_id', $customerClientIds->all());
                });
            }
        });
    
        // Si no quieres incluir al usuario logueado, descomenta:
        // $q->where('users.id', '!=', $userId);
    
        return $q->orderBy('users.name')->get();
    }
    
    /**
     * Etiqueta de prioridad legible (1-5) segun idioma. Nunca devuelve numero ni '-'.
     */
    private function priorityLabel($priority, string $lang = 'es'): string
    {
        $labels = [
            'es' => [1 => 'Muy baja', 2 => 'Baja', 3 => 'Regular', 4 => 'Alta', 5 => 'Muy alta'],
            'en' => [1 => 'Very Low', 2 => 'Low', 3 => 'Regular', 4 => 'High', 5 => 'Very High'],
        ];
        $lang = isset($labels[$lang]) ? $lang : 'es';
        return $labels[$lang][(int) $priority] ?? $labels[$lang][3];
    }

    /**
     * Construye un bloque "etiqueta + recuadro" para correos. Devuelve '' si el texto
     * viene vacio (asi no aparece un recuadro vacio). $rawHtml indica si el contenido
     * ya es HTML (descripcion/respuesta) o texto plano (comentario) que hay que escapar.
     */
    private function emailNoteBlock(string $label, ?string $content, bool $rawHtml = false, string $accentBg = '#f9fafb'): string
    {
        $plain = trim(strip_tags(str_replace('&nbsp;', ' ', (string) $content)));
        if ($plain === '') {
            return '';
        }
        $inner = $rawHtml ? $content : nl2br(e(trim((string) $content)));

        return '<p style="font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;'
            . 'color:#8a909c;margin:18px 0 6px;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">' . $label . '</p>'
            . '<div style="background:' . $accentBg . ';border:1px solid #e5e7eb;border-left:4px solid #6676ef;'
            . 'border-radius:8px;padding:14px 16px;font-size:14px;color:#111827;line-height:1.55;'
            . 'word-break:break-word;overflow-wrap:break-word;font-family:Arial,Helvetica,sans-serif;">'
            . $inner . '</div>';
    }

    private function sendAssignedTicketEmail(HelpdeskTicket $ticket, ?string $assignmentComment = null)
    {
        try {
            if (empty($ticket->user_id)) {
                return;
            }
    
            if (empty(admin_setting('New Helpdesk Ticket Assigned')) || admin_setting('New Helpdesk Ticket Assigned') != true) {
                return;
            }
    
            $assignedUser = User::find($ticket->user_id);
            $creatorUser  = !empty($ticket->created_by) ? User::find($ticket->created_by) : null;
    
            if (!$assignedUser || empty($assignedUser->email)) {
                return;
            }
    
            $ticketUrl = route('helpdesk.view', [Crypt::encrypt($ticket->ticket_id)]);

            // Prioridad legible (mismas etiquetas que la pantalla del ticket), segun el idioma del destinatario
            $recipientLang = substr((string) (optional($assignedUser)->lang ?: 'es'), 0, 2);
            $priorityText = $this->priorityLabel($ticket->priority, $recipientLang);

            // Descripcion del ticket (se guarda como HTML); '-' si viene vacia
            $descriptionHtml = trim((string) $ticket->description) !== '' ? $ticket->description : '-';

            // Creado por: usuario que lo creo; si no resuelve (p.ej. ticket por correo),
            // usar el nombre/correo de quien abrio el ticket
            $createdByName = optional($creatorUser)->name
                ?: ($ticket->name ?: ($ticket->email ?: '-'));

            // Comentario de la asignacion: bloque completo si viene, vacio si no
            $commentLabel = $recipientLang === 'en' ? 'Assignment note' : 'Comentario de asignación';
            $assignmentCommentHtml = $this->emailNoteBlock($commentLabel, $assignmentComment, false, '#eef1ff');

            $uArr = [
                'ticket_name'         => $assignedUser->name,
                'ticket_id'           => $ticket->ticket_id,
                'ticket_url'          => $ticketUrl,
                'email'               => $assignedUser->email,
                'subject'             => $ticket->subject,
                'status'              => $ticket->status,
                'priority'            => $priorityText,
                'description'         => $descriptionHtml,
                'assignment_comment'  => $assignmentCommentHtml,
                'assigned_user_name'  => $assignedUser->name,
                'created_user_name'   => $createdByName,
                'company_name'        => company_setting('company_name', $ticket->created_by),
            ];
    
            EmailTemplate::sendEmailTemplate(
                'New Helpdesk Ticket Assigned',
                [$assignedUser->email],
                $uArr,
                Auth::id() ?: $ticket->created_by
            );
    
        } catch (\Throwable $e) {
            \Log::error('Error sending assigned ticket email', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private function sendReplyNotificationToTicketCreator(HelpdeskTicket $ticket, string $replyDescription)
    {
        try {
            if (empty($ticket->created_by)) {
                return;
            }
    
            $creatorUser = User::find($ticket->created_by);
    
            if (!$creatorUser || empty($creatorUser->email)) {
                return;
            }

            $lang = substr((string) ($creatorUser->lang ?: 'es'), 0, 2);
            $messageLabel = $lang === 'en' ? 'Message' : 'Mensaje';
    
            $uArr = [
                'ticket_name'       => $creatorUser->name,
                'ticket_id'         => $ticket->ticket_id,
                'email'             => $creatorUser->email,
                'reply_message'     => $this->emailNoteBlock($messageLabel, $replyDescription, true),
                'subject'           => $ticket->subject,
                'status'            => $ticket->status,
                'priority'          => $this->priorityLabel($ticket->priority, $lang),
                'company_name'      => company_setting('company_name', $ticket->created_by),
            ];
    
            EmailTemplate::sendEmailTemplate(
                'New Helpdesk Ticket Reply',
                [$creatorUser->email],
                $uArr,
                Auth::id() ?: $ticket->created_by
            );
        } catch (\Exception $e) {
            \Log::error('Error sending reply notification to ticket creator: ' . $e->getMessage());
        }
    }
    
    private function getTicketScopeQuery()
    {
        $user = Auth::user();
        $tickets = HelpdeskTicket::query();
    
        if (!$user) {
            return $tickets->whereRaw('1 = 0');
        }
    
        if (\Laratrust::hasRole('super admin') || strtolower((string) ($user->type ?? '')) === 'company') {
            return $tickets;
        }
    
        $tickets->where(function ($q) use ($user) {
            $q->where('helpdesk_tickets.user_id', $user->id)
              ->orWhere('helpdesk_tickets.created_by', $user->id)
              ->orWhereRaw('(JSON_VALID(helpdesk_tickets.visible_to_user_ids) AND JSON_CONTAINS(helpdesk_tickets.visible_to_user_ids, ?))', [json_encode((int) $user->id)])
              ->orWhereRaw('(JSON_VALID(helpdesk_tickets.touched_by_user_ids) AND JSON_CONTAINS(helpdesk_tickets.touched_by_user_ids, ?))', [json_encode((int) $user->id)])
              ->orWhereExists(function ($sq) use ($user) {
                  $sq->from('customer_clients_assigned as cca')
                     ->whereColumn('cca.customer_client_id', 'helpdesk_tickets.customer_client_id')
                     ->where('cca.user_id', $user->id);
              })
              ->orWhereExists(function ($sq) use ($user) {
                  $sq->from('customer_clients_users as ccu')
                     ->whereColumn('ccu.customer_client_id', 'helpdesk_tickets.customer_client_id')
                     ->where('ccu.user_id', $user->id);
              });
        });
    
        return $tickets;
    }

    private function canEditHeaderActions(HelpdeskTicket $ticket): bool
    {
        $user = Auth::user();
    
        if (!$user) {
            return false;
        }
    
        if (in_array(strtolower((string) ($user->type ?? '')), ['super admin', 'company'])) {
            return true;
        }
        
        if ($ticket->status === 'Closed') {
            return false;
        }
    
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }
    

    
        if (\Laratrust::hasRole('consultant')) {
            return true;
        }
    
        if (!empty($ticket->customer_client_id)) {
            return CustomerClientAssigned::where('customer_client_id', $ticket->customer_client_id)
                ->where('user_id', $user->id)
                ->exists();
        }
    
        return false;
    }
    
    private function canChangeCategoryAndPriority(): bool
    {
        $user = Auth::user();
    
        if (!$user) {
            return false;
        }
    
        return \Laratrust::hasRole('super admin')
            || strtolower((string) ($user->type ?? '')) === 'company'
            || \Laratrust::hasRole('consultant');
    }
    
    private function canReplyTicket(HelpdeskTicket $ticket): bool
    {
        $user = Auth::user();
    
        if (!$user) {
            return false;
        }
    
        if ((int) $ticket->user_id === (int) $user->id || ((int) $ticket->created_by === (int) $user->id) && strtolower($ticket->status) === 'new') {
            return true;
        }
    
        if (\Laratrust::hasRole('super admin') || strtolower((string) ($user->type ?? '')) === 'company') {
            return true;
        }
    
        return false;
    }

    private function getAssignableUsersForTicket(HelpdeskTicket $ticket)
    {
        $customerId = $ticket->customer_id;
    
        if (!$customerId && $ticket->customer_client_id) {
            $customerId = DB::table('customer_clients')
                ->where('id', $ticket->customer_client_id)
                ->value('customer_id');
        }
    
        if (!$customerId) {
            return collect();
        }
    
        return DB::table('users')
            ->join('customers as c', 'c.user_id', '=', 'users.id')
            ->where('c.id', $customerId)
            ->select('users.id', 'users.name', 'users.email')
            ->union(
                DB::table('users')
                    ->join('customer_clients_assigned as cca', 'cca.user_id', '=', 'users.id')
                    ->join('customer_clients as cc', 'cc.id', '=', 'cca.customer_client_id')
                    ->where('cc.customer_id', $customerId)
                    ->select('users.id', 'users.name', 'users.email')
            )
            ->orderBy('name')
            ->get();
    }

    private function normalizeVisibleUsersFromRequest(Request $request): array
    {
        return collect($request->input('visible_to_user_ids', []))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
    
    private function resolveAssignedUserForManualTicket(Request $request): ?int
    {
        $assignedUserId = $this->canAssignUsers()
            ? (int) $request->input('assigned_user_id')
            : null;
    
        if (!empty($assignedUserId)) {
            return $assignedUserId;
        }
    
        $customerClientId = (int) $request->input('customer_client_id');
        if ($customerClientId > 0) {
            $assigned = DB::table('customer_clients_assigned')
                ->where('customer_client_id', $customerClientId)
                ->orderBy('id')
                ->value('user_id');
    
            if (!empty($assigned)) {
                return (int) $assigned;
            }
        }
    
        return null;
    }
    
    private function isCompanyLike(User $user = null): bool
    {
        $user = $user ?: Auth::user();
    
        if (!$user) {
            return false;
        }
    
        return \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || strtolower(trim((string) $user->type)) === 'company';
    }   
    
    private function sendReplyNotificationToTicketEmail(HelpdeskTicket $ticket, string $replyDescription)
    {
        try {
            if (empty($ticket->email)) {
                return;
            }
    
            $ticketUrl = route('helpdesk.view', [Crypt::encrypt($ticket->ticket_id)]);
    
            $uArr = [
                'ticket_name'       => $ticket->name ?: $ticket->email,
                'ticket_id'         => $ticket->ticket_id,
                'ticket_url'        => $ticketUrl,
                'email'             => $ticket->email,
                'reply_message'     => $this->emailNoteBlock('Mensaje', $replyDescription, true),
                'subject'           => $ticket->subject,
                'status'            => $ticket->status,
                'priority'          => $this->priorityLabel($ticket->priority, 'es'),
                'company_name'      => company_setting('company_name', $ticket->created_by),
            ];
    
            EmailTemplate::sendEmailTemplate(
                'New Helpdesk Ticket Reply',
                [$ticket->email],
                $uArr,
                Auth::id() ?: $ticket->created_by
            );
        } catch (\Throwable $e) {
            \Log::error('Error sending reply notification to ticket email', [
                'ticket_id' => $ticket->id ?? null,
                'email' => $ticket->email ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    } 
    
    private function canViewTimesheetsSection(): bool
    {
        $user = Auth::user();
    
        if (!$user) {
            return false;
        }
    
        return
            \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || \Laratrust::hasRole('consultant')
            || \Laratrust::hasRole('Consultant')
            || \Laratrust::hasRole('staff')
            || \Laratrust::hasRole('Staff')
            || strtolower((string) ($user->type ?? '')) === 'staff';
    }

    private function getAvailableParentTickets(
        ?int $excludeId = null,
        ?int $taskId = null,
        ?int $customerId = null,
        ?int $customerClientId = null
    ) {
        $query = $this->getTicketScopeQuery()
            ->leftJoin('users as creator_user', 'creator_user.id', '=', 'helpdesk_tickets.created_by')
            ->select(
                'helpdesk_tickets.id',
                'helpdesk_tickets.ticket_id',
                'helpdesk_tickets.subject',
                'helpdesk_tickets.description',
                'helpdesk_tickets.task_id',
                'helpdesk_tickets.customer_id',
                'helpdesk_tickets.customer_client_id',
                'creator_user.name as created_by_name'
            )
            ->whereNull('helpdesk_tickets.parent_ticket_id')
            ->orderByDesc('helpdesk_tickets.id');
    
        if (!empty($excludeId)) {
            $query->where('helpdesk_tickets.id', '!=', $excludeId);
        }
    
        if (!empty($taskId) || !empty($customerId) || !empty($customerClientId)) {
            $query->where(function ($q) use ($taskId, $customerId, $customerClientId) {
                if (!empty($taskId)) {
                    $q->orWhere('helpdesk_tickets.task_id', $taskId);
                }
    
                if (!empty($customerId)) {
                    $q->orWhere('helpdesk_tickets.customer_id', $customerId);
                }
    
                if (!empty($customerClientId)) {
                    $q->orWhere('helpdesk_tickets.customer_client_id', $customerClientId);
                }
            });
        }
    
        return $query->get()->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_public_id' => $ticket->ticket_id,
                'label' => '#' . $ticket->ticket_id . ' - ' . $ticket->subject,
                'description' => $ticket->description ?? '',
                'created_by_name' => $ticket->created_by_name ?? '---',
            ];
        })->values();
    }

}
