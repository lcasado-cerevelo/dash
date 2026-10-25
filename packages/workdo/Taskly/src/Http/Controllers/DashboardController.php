<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\ClientProject;
use Workdo\Taskly\Entities\Stage;
use Workdo\Taskly\Entities\Task;
use Workdo\Taskly\Entities\UserProject;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\User;
use App\Models\HelpdeskTicket as Ticket;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Workdo\Timesheet\Entities\Timesheet;

class DashboardController extends Controller
{
    public function __construct()
    {
        if (module_is_active('GoogleAuthentication')) {
            $this->middleware('2fa');
        }
    }

    public function index()
    {
        if (!Auth::user()->isAbleTo('taskly dashboard manage')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    
        $userObj          = Auth::user();
        $currentWorkspace = getActiveWorkSpace();
        $userId           = (int) $userObj->id;
        $ws               = $currentWorkspace;
        $userType         = strtolower(trim((string) $userObj->type));
    
        $isCompanyByRole    = $userObj->hasRole('company') || $userObj->hasRole('Company');
        $isCompanyByType    = $userType === 'company';
        $isCompanyLike      = $isCompanyByRole || $isCompanyByType;
    
        $isConsultantByRole = $userObj->hasRole('consultant') || $userObj->hasRole('Consultant');
        $isClientByRole     = $userObj->hasRole('client') || $userObj->hasRole('Client');
        $isClientByType     = $userType === 'client';
        $isClientLike       = $isClientByRole || $isClientByType;
    
        $isCustomerByRole   = $userObj->hasRole('customer') || $userObj->hasRole('Customer');
        $isSuperAdmin       = $userObj->hasRole('super admin');
        $isSuperOrCompany   = $isSuperAdmin || $isCompanyLike;
        $isStaff            = $userObj->hasRole('Staff') || $userObj->hasRole('staff');
        $canViewTimesheets = $this->canViewTimesheetsSection();
        
        $findUserInSerializedArraySql = function ($column) {
            return "
                FIND_IN_SET(
                    ?,
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(COALESCE({$column}, ''), '\"', ''),
                            '[', ''),
                        ']', ''),
                    ' ', '')
                ) > 0
            ";
        };
    
    
    /*
    |--------------------------------------------------------------------------
    | Branch para Staff
    |--------------------------------------------------------------------------
    */
    if ($isStaff) {
    
        $customerIds = DB::table('customer_clients_users as ccu')
            ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
            ->where('ccu.user_id', $userId)
            ->pluck('cc.customer_id')
            ->unique()
            ->values();
    
        $customerClientIds = DB::table('customer_clients_users')
            ->where('user_id', $userId)
            ->pluck('customer_client_id')
            ->unique()
            ->values();
    
        $projectIdsFromMembership = DB::table('user_projects')
            ->join('projects', 'projects.id', '=', 'user_projects.project_id')
            ->where('user_projects.user_id', '=', $userId)
            ->where('projects.workspace', '=', $currentWorkspace)
            ->where('projects.type', '=', 'project')
            ->pluck('projects.id');
    
        $projectIdsFromCustomer = collect();
        if ($customerIds->isNotEmpty() && Schema::hasColumn('projects', 'customer_id')) {
            $projectIdsFromCustomer = DB::table('projects')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->whereIn('projects.customer_id', $customerIds->all())
                ->pluck('projects.id');
        }
    
        $projectIds = $projectIdsFromMembership
            ->merge($projectIdsFromCustomer)
            ->unique()
            ->values();
    
        $doneStage = Stage::where('workspace_id', $currentWorkspace)
            ->where(function ($q) {
                $q->where('complete', '1')
                  ->orWhere('name', 'Done');
            })
            ->first();
    
        $totalProject = $projectIds->count();
    
        $totalBugs = DB::table('bug_reports')
            ->whereIn('project_id', $projectIds)
            ->count();
    
        $staffTasksBase = Task::select([
                'tasks.*',
                'stages.name as status',
                'stages.complete',
            ])
            ->join('stages', 'stages.id', '=', 'tasks.status')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereIn('tasks.project_id', $projectIds)
            ->where('projects.workspace', '=', $currentWorkspace)
            ->where('projects.type', '=', 'project')
            ->where('tasks.is_service', '=', 0);
    
        if (!empty($doneStage)) {
            $staffTasksBase->where('tasks.status', '!=', $doneStage->id);
        }
    
        $staffTasksBase->where(function ($q) {
            $q->whereNull('stages.complete')
              ->orWhere('stages.complete', '!=', 1);
        });
    
        $staffTasksBase->whereRaw("LOWER(TRIM(COALESCE(stages.name, ''))) <> 'done'");
    
        $tasks = (clone $staffTasksBase)
            ->orderByDesc('tasks.id')
            ->with('project')
            ->get();
    
        $totaltasks   = $tasks->count();
        $totalTask    = (clone $staffTasksBase)->count();
        $completeTask = 0;
    
        $excludedStatuses = ['closed', 'canceled'];
    
        $staffTicketsBase = DB::table('helpdesk_tickets as t')
            ->select('t.id', 't.ticket_id', 't.subject', 't.status', 't.priority', 't.created_at', 't.user_id')
            ->where('t.workspace', '=', $currentWorkspace)
            ->where(function ($q) use ($customerIds, $customerClientIds, $projectIds) {
                if ($customerIds->isNotEmpty()) {
                    $q->orWhereIn('t.customer_id', $customerIds->all());
                }
    
                if ($customerClientIds->isNotEmpty()) {
                    $q->orWhereIn('t.customer_client_id', $customerClientIds->all());
                }
    
                if ($projectIds->isNotEmpty()) {
                    $q->orWhereExists(function ($sq) use ($projectIds) {
                        $sq->from('tasks as tk')
                           ->whereColumn('tk.id', 't.task_id')
                           ->whereIn('tk.project_id', $projectIds->all());
                    });
                }
            })
            ->where(function ($q) use ($excludedStatuses) {
                $placeholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
    
                $q->whereNull('t.status')
                  ->orWhereRaw(
                      'TRIM(LOWER(t.status)) NOT IN (' . $placeholders . ')',
                      $excludedStatuses
                  );
            });
    
        $myTickets = (clone $staffTicketsBase)
            ->orderByRaw('CASE WHEN t.user_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderByDesc('t.created_at')
            ->get();
            
        $totalTicketAssigned  = $myTickets->count();
        $totalTicketsIncluded = (clone $staffTicketsBase)->count();
    
        $timesheetBase = DB::table('timesheets')
            ->when(Schema::hasColumn('timesheets', 'workspace_id'), function ($q) use ($currentWorkspace) {
                $q->where('workspace_id', $currentWorkspace);
            })
            ->where('created_by', creatorId())
            ->where('validated', false);
    
        $timesheetBase->where(function ($w) use ($projectIds, $customerIds, $customerClientIds) {
            if ($projectIds->isNotEmpty() && Schema::hasColumn('timesheets', 'project_id')) {
                $w->orWhereIn('project_id', $projectIds->all());
            }
    
            if ($customerIds->isNotEmpty() && Schema::hasColumn('timesheets', 'customer_id')) {
                $w->orWhereIn('customer_id', $customerIds->all());
            }
    
            if ($customerClientIds->isNotEmpty() && Schema::hasColumn('timesheets', 'customer_client_id')) {
                $w->orWhereIn('customer_client_id', $customerClientIds->all());
            }
        });
    
        $totalTimesheetsToApprove = (clone $timesheetBase)->count();
    
        $projectProcess = DB::table('projects')
            ->selectRaw('count(id) as count, status')
            ->whereIn('id', $projectIds)
            ->groupBy('status')
            ->pluck('count', 'status');
    
        $arrProcessPer   = [];
        $arrProcessLabel = [];
    
        if ($projectProcess->count() > 0) {
            foreach ($projectProcess as $label => $count) {
                $arrProcessLabel[] = $label;
                $arrProcessPer[]   = $totalProject ? round(($count * 100) / $totalProject, 2) : 0.00;
            }
        } else {
            $arrProcessPer   = [100];
            $arrProcessLabel = [''];
        }
    
        $arrProcessClass = ['text-success', 'text-primary', 'text-danger'];
    
        $chartData = app('Workdo\Taskly\Http\Controllers\ProjectController')->getProjectChart([
            'workspace_id' => $currentWorkspace,
            'duration'     => 'week',
        ]);
    
        $totalMembers = 0;
    
        return view('taskly::index', compact(
            'currentWorkspace',
            'totalProject',
            'totalBugs',
            'totalTask',
            'totalTimesheetsToApprove',
            'arrProcessLabel',
            'arrProcessPer',
            'arrProcessClass',
            'completeTask',
            'tasks',
            'chartData',
            'totaltasks',
            'myTickets',
            'totalTicketsIncluded',
            'totalTicketAssigned',
            'totalMembers',
            'canViewTimesheets'
        ));
    }
    
         /*
        |--------------------------------------------------------------------------
        | Branch para Consultant
        |--------------------------------------------------------------------------
        */
        if ($isConsultantByRole) {
        
            $projectIds = DB::table('user_projects')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->where('user_projects.user_id', '=', $userId)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->pluck('projects.id')
                ->unique()
                ->values();
        
            $doneStage = Stage::where('workspace_id', $currentWorkspace)
                ->where(function ($q) {
                    $q->where('complete', '1')
                      ->orWhere('name', 'Done');
                })
                ->first();
        
            $totalProject = $projectIds->count();
        
            $totalBugs = DB::table('bug_reports')
                ->whereIn('project_id', $projectIds)
                ->count();
        
            $consultantTasksBase = Task::select([
                    'tasks.*',
                    'stages.name as status',
                    'stages.complete',
                ])
                ->join('stages', 'stages.id', '=', 'tasks.status')
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->whereIn('tasks.project_id', $projectIds)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', '=', 0)
                ->where(function ($q) use ($userId) {
                    $q->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tasks.assign_to,''), ' ', '')) > 0", [$userId])
                      ->orWhereExists(function ($sq) use ($userId) {
                          $sq->from('user_projects as up')
                             ->whereColumn('up.project_id', 'tasks.project_id')
                             ->where('up.user_id', $userId);
                      });
                });
        
            if (!empty($doneStage)) {
                $consultantTasksBase->where('tasks.status', '!=', $doneStage->id);
            }
        
            $consultantTasksBase->where(function ($q) {
                $q->whereNull('stages.complete')
                  ->orWhere('stages.complete', '!=', 1);
            });
        
            $consultantTasksBase->whereRaw("LOWER(TRIM(COALESCE(stages.name, ''))) <> 'done'");
        
            $tasks = (clone $consultantTasksBase)
                ->orderByDesc('tasks.id')
                ->with('project')
                ->get();
        
            $totaltasks   = $tasks->count();
            $totalTask    = (clone $consultantTasksBase)->count();
            $completeTask = 0;
        
            $excludedStatuses = ['closed', 'canceled'];
        
            $consultantTicketsBase = DB::table('helpdesk_tickets as t')
                ->select('t.id', 't.ticket_id', 't.subject', 't.status', 't.priority', 't.created_at', 't.user_id')
                ->where('t.workspace', '=', $currentWorkspace)
                ->where(function ($q) use ($userId, $findUserInSerializedArraySql) {
                    $q->where('t.user_id', $userId)
                      ->orWhere('t.created_by', $userId)
                      ->orWhereRaw($findUserInSerializedArraySql('t.visible_to_user_ids'), [$userId])
                      ->orWhereRaw($findUserInSerializedArraySql('t.touched_by_user_ids'), [$userId]);
                })
                ->where(function ($q) use ($excludedStatuses) {
                    $placeholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
        
                    $q->whereNull('t.status')
                      ->orWhereRaw(
                          'TRIM(LOWER(t.status)) NOT IN (' . $placeholders . ')',
                          $excludedStatuses
                      );
                });
        
            $myTickets = (clone $consultantTicketsBase)
                ->orderByRaw('CASE WHEN t.user_id = ? THEN 0 ELSE 1 END', [$userId])
                ->orderByDesc('t.created_at')
                ->get();
        
            $totalTicketAssigned  = $myTickets->count();
            $totalTicketsIncluded = (clone $consultantTicketsBase)->count();
        
            // AQUÍ ESTÁ LA PARTE IMPORTANTE:
            $timesheetBase = DB::table('timesheets')
                ->when(Schema::hasColumn('timesheets', 'workspace_id'), function ($q) use ($currentWorkspace) {
                    $q->where('workspace_id', $currentWorkspace);
                })
                ->where('created_by', creatorId())
                ->where('user_id', $userId);
        
            if (Schema::hasColumn('timesheets', 'is_validated')) {
                $timesheetBase->where('is_validated', 0);
            } elseif (Schema::hasColumn('timesheets', 'validated')) {
                $timesheetBase->where('validated', 0);
            }
        
            $totalTimesheetsToApprove = (clone $timesheetBase)->count();
        
            $projectProcess = DB::table('projects')
                ->selectRaw('count(id) as count, status')
                ->whereIn('id', $projectIds)
                ->groupBy('status')
                ->pluck('count', 'status');
        
            $arrProcessPer   = [];
            $arrProcessLabel = [];
        
            if ($projectProcess->count() > 0) {
                foreach ($projectProcess as $label => $count) {
                    $arrProcessLabel[] = $label;
                    $arrProcessPer[]   = $totalProject ? round(($count * 100) / $totalProject, 2) : 0.00;
                }
            } else {
                $arrProcessPer   = [100];
                $arrProcessLabel = [''];
            }
        
            $arrProcessClass = ['text-success', 'text-primary', 'text-danger'];
        
            $chartData = app('Workdo\Taskly\Http\Controllers\ProjectController')->getProjectChart([
                'workspace_id' => $currentWorkspace,
                'duration'     => 'week',
            ]);
        
            $totalMembers = 0;
        
            return view('taskly::index', compact(
                'currentWorkspace',
                'totalProject',
                'totalBugs',
                'totalTask',
                'totalTimesheetsToApprove',
                'arrProcessLabel',
                'arrProcessPer',
                'arrProcessClass',
                'completeTask',
                'tasks',
                'chartData',
                'totaltasks',
                'myTickets',
                'totalTicketsIncluded',
                'totalTicketAssigned',
                'totalMembers',
                'canViewTimesheets'
            ));
        }
 
    
        /*
        |--------------------------------------------------------------------------
        | Branch para Client / Customer
        |--------------------------------------------------------------------------
        */
        
        
        if ($isClientLike || $isCustomerByRole) {
    
            $customerIds = collect();
            $ccIds       = collect();
    
            if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'user_id')) {
                $customerIds = DB::table('customers')
                    ->where('user_id', $userId)
                    ->pluck('id');
            }
    
            if (Schema::hasTable('customer_clients_users')) {
                $ccIds = DB::table('customer_clients_users')
                    ->where('user_id', $userId)
                    ->pluck('customer_client_id');
            }
    
            if ($customerIds->isEmpty() && $ccIds->isNotEmpty() && Schema::hasTable('customer_clients')) {
                $customerIds = DB::table('customer_clients')
                    ->whereIn('id', $ccIds->all())
                    ->pluck('customer_id')
                    ->unique()
                    ->values();
            }
    
            if ($isClientLike) {
                $projectIds = DB::table('projects')
                    ->join('client_projects', 'projects.id', '=', 'client_projects.project_id')
                    ->where('client_projects.client_id', '=', $userId)
                    ->where('projects.workspace', '=', $currentWorkspace)
                    ->where('projects.type', '=', 'project')
                    ->pluck('projects.id');
            } else {
                $projectIds = DB::table('projects')
                    ->join('user_projects', 'projects.id', '=', 'user_projects.project_id')
                    ->where('user_projects.user_id', '=', $userId)
                    ->where('projects.workspace', '=', $currentWorkspace)
                    ->where('projects.type', '=', 'project')
                    ->pluck('projects.id');
            }
    
            $projectIds = $projectIds->unique()->values();
    
            $doneStage = Stage::where('workspace_id', $currentWorkspace)
                ->where(function ($q) {
                    $q->where('complete', '1')
                      ->orWhere('name', 'Done');
                })
                ->first();
    
            $totalProject = $projectIds->count();
    
            $totalBugs = DB::table('bug_reports')
                ->whereIn('project_id', $projectIds)
                ->count();
    
            $totalTaskQuery = Task::join('projects', 'projects.id', '=', 'tasks.project_id')
                ->whereIn('tasks.project_id', $projectIds)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', '=', 0);
    
            $totalTaskNotDone = (clone $totalTaskQuery)
                ->when($doneStage, function ($q) use ($doneStage) {
                    $q->where('tasks.status', '!=', $doneStage->id);
                })
                ->count();
    
            $tasks = Task::select([
                    'tasks.*',
                    'stages.name as status',
                    'stages.complete',
                ])
                ->join('stages', 'stages.id', '=', 'tasks.status')
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->whereIn('tasks.project_id', $projectIds)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', '=', 0)
                ->when($doneStage, function ($q) use ($doneStage) {
                    $q->where('tasks.status', '!=', $doneStage->id);
                })
                ->orderByDesc('tasks.id')
                ->with('project')
                ->get();
    
            $totaltasks   = $tasks->count();
            $completeTask = 0;
    
            $ticketCols = [
                't.id',
                't.ticket_id',
                't.subject',
                't.status',
                't.created_at',
                't.user_id',
            ];
    
            if (Schema::hasColumn('helpdesk_tickets', 'priority')) {
                $ticketCols[] = 't.priority';
            }
    
            $ticketBase = DB::table('helpdesk_tickets as t')
                ->select($ticketCols)
                ->distinct();
    
            if (Schema::hasColumn('helpdesk_tickets', 'workspace')) {
                $ticketBase->where('t.workspace', $ws);
            }
    
            $excludedStatuses = ['closed', 'canceled'];
    
            if ($isClientLike) {
                $ticketsIncluded = (clone $ticketBase)
                    ->where(function ($w) use ($userId, $findUserInSerializedArraySql) {
                        $w->where('t.user_id', $userId)
                          ->orWhere('t.created_by', $userId)
                          ->orWhereRaw($findUserInSerializedArraySql('t.visible_to_user_ids'), [$userId])
                          ->orWhereRaw($findUserInSerializedArraySql('t.touched_by_user_ids'), [$userId])
                          ->orWhereExists(function ($sq) use ($userId) {
                              $sq->from('helpdesk_conversions as hc')
                                 ->whereColumn('hc.ticket_id', 't.id')
                                 ->where('hc.sender', (string) $userId);
                          });
                    })
                    ->where(function ($q) use ($excludedStatuses) {
                        $placeholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
    
                        $q->whereNull('t.status')
                          ->orWhereRaw(
                              'TRIM(LOWER(t.status)) NOT IN (' . $placeholders . ')',
                              $excludedStatuses
                          );
                    });
    
                $ticketIdsForUser = DB::table('helpdesk_tickets as ht')
                    ->when(Schema::hasColumn('helpdesk_tickets', 'workspace'), function ($q) use ($currentWorkspace) {
                        $q->where('ht.workspace', $currentWorkspace);
                    })
                    ->where(function ($w) use ($userId, $findUserInSerializedArraySql) {
                        $w->where('ht.user_id', $userId)
                          ->orWhere('ht.created_by', $userId)
                          ->orWhereRaw($findUserInSerializedArraySql('ht.visible_to_user_ids'), [$userId])
                          ->orWhereRaw($findUserInSerializedArraySql('ht.touched_by_user_ids'), [$userId])
                          ->orWhereExists(function ($sq) use ($userId) {
                              $sq->from('helpdesk_conversions as hc')
                                 ->whereColumn('hc.ticket_id', 'ht.id')
                                 ->where('hc.sender', (string) $userId);
                          });
                    })
                    ->pluck('ht.id');
    
            } else {
                $ticketsIncluded = (clone $ticketBase)
                    ->where(function ($w) use ($userId, $ccIds, $customerIds, $findUserInSerializedArraySql) {
                        $w->where('t.user_id', $userId)
                          ->orWhere('t.created_by', $userId)
                          ->orWhereRaw($findUserInSerializedArraySql('t.visible_to_user_ids'), [$userId])
                          ->orWhereRaw($findUserInSerializedArraySql('t.touched_by_user_ids'), [$userId]);
    
                        if ($customerIds->isNotEmpty() && Schema::hasColumn('helpdesk_tickets', 'customer_id')) {
                            $w->orWhereIn('t.customer_id', $customerIds->all());
                        }
    
                        if ($ccIds->isNotEmpty() && Schema::hasColumn('helpdesk_tickets', 'customer_client_id')) {
                            $w->orWhereIn('t.customer_client_id', $ccIds->all());
                        }
                    })
                    ->where(function ($q) use ($excludedStatuses) {
                        $placeholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
    
                        $q->whereNull('t.status')
                          ->orWhereRaw(
                              'TRIM(LOWER(t.status)) NOT IN (' . $placeholders . ')',
                              $excludedStatuses
                          );
                    });
    
                $ticketIdsForUser = DB::table('helpdesk_tickets as ht')
                    ->when(Schema::hasColumn('helpdesk_tickets', 'workspace'), function ($q) use ($currentWorkspace) {
                        $q->where('ht.workspace', $currentWorkspace);
                    })
                    ->where(function ($w) use ($userId, $ccIds, $customerIds, $findUserInSerializedArraySql) {
                        $w->where('ht.user_id', $userId)
                          ->orWhere('ht.created_by', $userId)
                          ->orWhereRaw($findUserInSerializedArraySql('ht.visible_to_user_ids'), [$userId])
                          ->orWhereRaw($findUserInSerializedArraySql('ht.touched_by_user_ids'), [$userId]);
    
                        if ($customerIds->isNotEmpty() && Schema::hasColumn('helpdesk_tickets', 'customer_id')) {
                            $w->orWhereIn('ht.customer_id', $customerIds->all());
                        }
    
                        if ($ccIds->isNotEmpty() && Schema::hasColumn('helpdesk_tickets', 'customer_client_id')) {
                            $w->orWhereIn('ht.customer_client_id', $ccIds->all());
                        }
                    })
                    ->pluck('ht.id');
            }
    
            $totalTicketsIncluded = (clone $ticketsIncluded)->count();
    
            $myTickets = (clone $ticketsIncluded)
                ->orderByRaw('CASE WHEN t.user_id = ? THEN 0 ELSE 1 END', [$userId])
                ->orderByDesc('t.created_at')
                ->get();
                
            $totalTicketAssigned = (clone $ticketsIncluded)->count();
    
            $timesheetBase = DB::table('timesheets')
                ->when(Schema::hasColumn('timesheets', 'workspace_id'), function ($q) use ($currentWorkspace) {
                    $q->where('workspace_id', $currentWorkspace);
                })
                ->where('created_by', creatorId())
                ->where('validated', false);
    
            $timesheetBase->where(function ($w) use ($projectIds, $ccIds, $customerIds, $ticketIdsForUser) {
                if ($projectIds->isNotEmpty() && Schema::hasColumn('timesheets', 'project_id')) {
                    $w->orWhereIn('project_id', $projectIds->all());
                }
    
                if ($ccIds->isNotEmpty() && Schema::hasColumn('timesheets', 'customer_client_id')) {
                    $w->orWhereIn('customer_client_id', $ccIds->all());
                }
    
                if ($customerIds->isNotEmpty() && Schema::hasColumn('timesheets', 'customer_id')) {
                    $w->orWhereIn('customer_id', $customerIds->all());
                }
    
                if ($ticketIdsForUser->isNotEmpty() && Schema::hasColumn('timesheets', 'ticket_id')) {
                    $w->orWhereIn('ticket_id', $ticketIdsForUser->all());
                }
            });
    
            $totalTimesheetsToApprove = (clone $timesheetBase)->count();
    
            $projectProcess = DB::table('projects')
                ->selectRaw('count(id) as count, status')
                ->whereIn('id', $projectIds)
                ->groupBy('status')
                ->pluck('count', 'status');
    
            $arrProcessPer   = [];
            $arrProcessLabel = [];
    
            if ($projectProcess->count() > 0) {
                foreach ($projectProcess as $label => $count) {
                    $arrProcessLabel[] = $label;
                    $arrProcessPer[]   = $totalProject ? round(($count * 100) / $totalProject, 2) : 0.00;
                }
            } else {
                $arrProcessPer   = [100];
                $arrProcessLabel = [''];
            }
    
            $arrProcessClass = ['text-success', 'text-primary', 'text-danger'];
    
            $chartData = app('Workdo\Taskly\Http\Controllers\ProjectController')->getProjectChart([
                'workspace_id' => $currentWorkspace,
                'duration'     => 'week',
            ]);
    
            $totalMembers = 0;
    
            return view('taskly::index', compact(
                'currentWorkspace',
                'totalProject',
                'totalBugs',
                'totalTaskNotDone',
                'totalTimesheetsToApprove',
                'arrProcessLabel',
                'arrProcessPer',
                'arrProcessClass',
                'completeTask',
                'tasks',
                'chartData',
                'totaltasks',
                'myTickets',
                'totalTicketsIncluded',
                'totalTicketAssigned',
                'canViewTimesheets'
            ));
        }
    
        /*
        |--------------------------------------------------------------------------
        | Resto de usuarios
        |--------------------------------------------------------------------------
        */
        $doneStage = Stage::where('workspace_id', $currentWorkspace)
            ->where(function ($q) {
                $q->where('complete', '1')
                  ->orWhere('name', 'Done');
            })
            ->first();
    
        if ($isCompanyLike) {
            $projectScope = DB::table('projects')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project');
    
            $totalProject = (clone $projectScope)->count();
    
            $totalBugs = DB::table('bug_reports')
                ->join('projects', 'projects.id', '=', 'bug_reports.project_id')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->count();
    
            $projectProcess = DB::table('projects')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->groupBy('projects.status')
                ->selectRaw('count(projects.id) as count, projects.status')
                ->pluck('count', 'projects.status');
    
            $totalTaskQuery = Task::join('projects', 'projects.id', '=', 'tasks.project_id')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', false);
    
            if (!empty($doneStage)) {
                $totalTaskQuery->where('tasks.status', '!=', $doneStage->id);
            }
    
            $totalTask = (clone $totalTaskQuery)->count();
    
            $tasks = Task::select([
                    'tasks.*',
                    'stages.name as status',
                    'stages.complete',
                ])
                ->join('projects', 'projects.id', '=', 'tasks.project_id')
                ->join('stages', 'stages.id', '=', 'tasks.status')
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', false)
                ->when(!empty($doneStage), function ($q) use ($doneStage) {
                    $q->where('tasks.status', '!=', $doneStage->id);
                })
                ->orderByDesc('tasks.id')
                ->with('project')
                ->get();
    
            $totalMembers = User::where('created_by', '=', creatorId())->emp()->count();
    
        } else {
            $projectMembership = DB::table('user_projects')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->where('user_projects.user_id', '=', $userObj->id)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project');
    
            $totalProject = (clone $projectMembership)->count();
    
            $totalBugs = DB::table('bug_reports')
                ->join('user_projects', 'user_projects.project_id', '=', 'bug_reports.project_id')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->where('user_projects.user_id', '=', $userObj->id)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->count();
    
            $projectProcess = DB::table('user_projects')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->where('user_projects.user_id', '=', $userObj->id)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->groupBy('projects.status')
                ->selectRaw('count(projects.id) as count, projects.status')
                ->pluck('count', 'projects.status');
    
            $totalTaskQuery = Task::join('user_projects', 'tasks.project_id', '=', 'user_projects.project_id')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->where('user_projects.user_id', '=', $userObj->id)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', false);
    
            if (!$isConsultantByRole) {
                $totalTaskQuery->whereRaw(
                    "FIND_IN_SET(?, REPLACE(COALESCE(tasks.assign_to,''), ' ', '')) > 0",
                    [$userObj->id]
                );
            }
    
            if (!empty($doneStage)) {
                $totalTaskQuery->where('tasks.status', '!=', $doneStage->id);
            }
    
            $totalTask = (clone $totalTaskQuery)->count();
    
            $tasks = Task::select([
                    'tasks.*',
                    'stages.name as status',
                    'stages.complete',
                ])
                ->join('user_projects', 'tasks.project_id', '=', 'user_projects.project_id')
                ->join('projects', 'projects.id', '=', 'user_projects.project_id')
                ->join('stages', 'stages.id', '=', 'tasks.status')
                ->where('user_projects.user_id', '=', $userObj->id)
                ->where('projects.workspace', '=', $currentWorkspace)
                ->where('projects.type', '=', 'project')
                ->where('tasks.is_service', false);
    
            if (!$isConsultantByRole) {
                $tasks->whereRaw(
                    "FIND_IN_SET(?, REPLACE(COALESCE(tasks.assign_to,''), ' ', '')) > 0",
                    [$userObj->id]
                );
            }
    
            $tasks = $tasks
                ->when(!empty($doneStage), function ($q) use ($doneStage) {
                    $q->where('tasks.status', '!=', $doneStage->id);
                })
                ->orderByDesc('tasks.id')
                ->with('project')
                ->get();
    
            $totalMembers = User::where('created_by', '=', creatorId())->emp()->count();
        }
    
        $totaltasks   = $tasks->count();
        $completeTask = 0;
    
        $excludedStatuses = ['closed', 'canceled'];
        
        $myTicketsQuery = Ticket::select('id', 'ticket_id', 'subject', 'status', 'priority', 'created_at', 'user_id')
            ->where('workspace', $currentWorkspace)
            ->where(function ($q) use ($excludedStatuses) {
                $placeholders = implode(',', array_fill(0, count($excludedStatuses), '?'));
        
                $q->whereNull('status')
                  ->orWhereRaw(
                      'TRIM(LOWER(status)) NOT IN (' . $placeholders . ')',
                      $excludedStatuses
                  );
            });
        
        if (!$isSuperOrCompany) {
            $myTicketsQuery->where(function ($q) use ($userObj, $userId, $findUserInSerializedArraySql) {
                $q->where('user_id', $userObj->id)
                  ->orWhere('created_by', $userObj->id)
                  ->orWhereRaw($findUserInSerializedArraySql('visible_to_user_ids'), [$userId])
                  ->orWhereRaw($findUserInSerializedArraySql('touched_by_user_ids'), [$userId]);
            });
        }
        
        $totalTicketAssigned = (clone $myTicketsQuery)->count();
        
        $myTickets = (clone $myTicketsQuery)
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderByDesc('created_at')
            ->get();
        
        $totalTicketAssigned = (clone $myTicketsQuery)->count();
    
        if (!empty($doneStage)) {
            $completeTask = $tasks->where('status', '=', $doneStage->name)->count();
        }
    
        $arrProcessLabel = [];
        $arrProcessPer   = [];
    
        if (count($projectProcess) > 0) {
            foreach ($projectProcess as $label => $process) {
                $arrProcessLabel[] = $label;
                $arrProcessPer[]   = $totalProject == 0 ? 0.00 : round(($process * 100) / $totalProject, 2);
            }
        } else {
            $arrProcessPer[0]   = 100;
            $arrProcessLabel[0] = '';
        }
    
        $arrProcessClass = [
            'text-success',
            'text-primary',
            'text-danger',
        ];
    
        $chartData = app('Workdo\Taskly\Http\Controllers\ProjectController')->getProjectChart([
            'workspace_id' => $currentWorkspace,
            'duration'     => 'week',
        ]);
    
        return view('taskly::index', compact(
            'currentWorkspace',
            'totalProject',
            'totalBugs',
            'totalTask',
            'totalMembers',
            'arrProcessLabel',
            'arrProcessPer',
            'arrProcessClass',
            'completeTask',
            'tasks',
            'chartData',
            'totaltasks',
            'myTickets',
            'totalTicketAssigned',
            'canViewTimesheets'
        ));
    }

    private function canViewTimesheetsSection(): bool
    {
        $user = Auth::user();
    
        if (!$user) {
            return false;
        }
    
        $type = strtolower(trim((string) $user->type));
    
        return
            $type === 'staff' ||
            \Laratrust::hasRole('company') ||
            \Laratrust::hasRole('Company') ||
            \Laratrust::hasRole('consultant') ||
            \Laratrust::hasRole('Consultant') ||
            \Laratrust::hasRole('staff') ||
            \Laratrust::hasRole('Staff');
    }
    }