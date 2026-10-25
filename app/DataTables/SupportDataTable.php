<?php

namespace App\DataTables;

use App\Models\HelpdeskTicket;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use App\Services\HelpdeskTicketService;

class SupportDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->editColumn('ticket_id', function (HelpdeskTicket $ticket) {
                $user = Auth::user();
            
                $isSuper = \Laratrust::hasRole('super admin')
                    || \Laratrust::hasRole('superadministrator')
                    || (isset($user->type) && strtolower(trim((string) $user->type)) === 'super admin');
            
                $isCompany = \Laratrust::hasRole('company')
                    || \Laratrust::hasRole('Company')
                    || (isset($user->type) && strtolower(trim((string) $user->type)) === 'company');
            
                $canReplyOrEdit = false;
            
                if ($isSuper || $isCompany) {
                    $canReplyOrEdit = true;
                } elseif ((int) $ticket->user_id === (int) $user->id) {
                    $canReplyOrEdit = true;
                }
            
                $url = $canReplyOrEdit
                    ? route('helpdesk.edit', $ticket->id)
                    : route('helpdesk.view', [\Illuminate\Support\Facades\Crypt::encrypt($ticket->ticket_id)]);
            
                return '<a class="btn btn-outline-primary w-100 text-center ticket-btn" href="' . $url . '">' .
                       e($ticket->ticket_id) .
                       '</a>';
            })
            ->editColumn('subject', function (HelpdeskTicket $ticket) {
                return '<div class="subject-cell" title="'.e($ticket->subject).'">'.e($ticket->subject).'</div>';
            })            
            ->editColumn('category', function (HelpdeskTicket $ticket) {
                return '<span class="badge badge-white p-2 px-3 fix_badge" style="background: '
                    .$ticket->color.';">'.$ticket->category_name.'</span>';
            })
            ->editColumn('priority', function (HelpdeskTicket $t) {
                $p = max(1, min(5, (int)$t->priority));
                $html = '';
                for ($i = 1; $i <= 5; $i++) {
                    $filled = $i <= $p ? 'text-warning' : 'text-muted';
                    $html  .= '<i class="ti ti-star '.$filled.'"></i>';
                }
                return $html;
            })
            ->editColumn('status', function (HelpdeskTicket $ticket) {
                $className = $ticket->status === 'In Progress' ? 'bg-warning'
                           : ($ticket->status === 'On Hold' ? 'bg-danger' :($ticket->status === 'Validation' ? 'bg-info' : ($ticket->status === 'Closed' ? 'bg-secondary' : ($ticket->status === 'Canceled' ? 'bg-dark' : 'bg-success'))));
                return '<span class="badge fix_badge p-2 px-3 '.$className.'">'.$ticket->status.'</span>';
            })
            ->editColumn('created_at', function (HelpdeskTicket $t) {
                return $t->created_at->diffForHumans();
            })
            ->addColumn('action', function (HelpdeskTicket $t) {
            
                $user = Auth::user();
                
                $isSuper = \Laratrust::hasRole('super admin')
                    || \Laratrust::hasRole('superadministrator')
                    || (isset($user->type) && strtolower(trim((string) $user->type)) === 'super admin');
                
                $isCompany = \Laratrust::hasRole('company')
                    || \Laratrust::hasRole('Company')
                    || (isset($user->type) && strtolower(trim((string) $user->type)) === 'company');
                    
                $canReply = false;
            
                if ($isSuper || $isCompany) {
                    $canReply = true;
                } elseif ((int)$t->user_id === (int)$user->id) {
                    $canReply = true;
                }
            
                $isViewOnly = HelpdeskTicketService::userIsViewOnlyForTicket($t, $user->id);
            
                return view('helpdesk_ticket.action', [
                    'ticket'     => $t,
                    'isViewOnly' => $isViewOnly,
                    'canReply'   => $canReply,   // 👈 NUEVO
                ]);
            })            
            ->rawColumns(['ticket_id','category','priority','status','action','subject']);

    }

    /**
     * Get the query source of dataTable.
     */
    public function query(HelpdeskTicket $model, Request $request)
    {
        $user = Auth::user();
        $workspaceId = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;
    
        $tickets = $model->select([
                'helpdesk_tickets.*',
                'helpdesk_ticket_categories.name as category_name',
                'helpdesk_ticket_categories.color',
                \DB::raw('COALESCE(au.name, au.email, CONCAT("User #", helpdesk_tickets.user_id)) as assigned_to')
            ])
            ->leftJoin('helpdesk_ticket_categories', 'helpdesk_ticket_categories.id', '=', 'helpdesk_tickets.category')
            ->leftJoin('users as au', 'au.id', '=', 'helpdesk_tickets.user_id');
    
        if (!empty($workspaceId)) {
            $tickets->where('helpdesk_tickets.workspace', $workspaceId);
        }
    
        if ($ids = (array) $request->input('assigned_to')) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!empty($ids)) {
                $tickets->whereIn('helpdesk_tickets.user_id', $ids);
            }
        }
    
        if ($cats = (array) $request->input('category_id')) {
            $cats = array_values(array_filter(array_map('intval', $cats)));
            if (!empty($cats)) {
                $tickets->whereIn('helpdesk_tickets.category', $cats);
            }
        }
    
        $from = $request->input('date_from');
        $to   = $request->input('date_to');
    
        if ($from && $to) {
            $tickets->whereBetween('helpdesk_tickets.created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        } elseif ($from) {
            $tickets->where('helpdesk_tickets.created_at', '>=', $from . ' 00:00:00');
        } elseif ($to) {
            $tickets->where('helpdesk_tickets.created_at', '<=', $to . ' 23:59:59');
        }
    
        $map = [
            'new'         => 'New',
            'in-progress' => 'In Progress',
            'on-hold'     => 'On Hold',
            'validation'  => 'Validation',
            'closed'      => 'Closed',
            'canceled'    => 'Canceled',
        ];
    
        $statuses = $request->input('statuses', []);
        if (!is_array($statuses) || empty($statuses)) {
            $slug = trim((string) $request->input('status', ''));
            if ($slug !== '' && strtolower($slug) !== 'all') {
                $statuses = [$slug];
            }
        }
    
        if (!empty($statuses)) {
            $vals = [];
            foreach ($statuses as $s) {
                $key = strtolower(trim((string) $s));
                if (isset($map[$key])) {
                    $vals[] = $map[$key];
                }
            }
    
            if (!empty($vals)) {
                $tickets->whereIn('helpdesk_tickets.status', array_unique($vals));
            }
        }
    
        $isSuper = \Laratrust::hasRole('super admin')
            || \Laratrust::hasRole('superadministrator')
            || (isset($user->type) && strtolower(trim((string) $user->type)) === 'super admin');
    
        $isCompany = \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || (isset($user->type) && strtolower(trim((string) $user->type)) === 'company');
    
        $isStaff = \Laratrust::hasRole('Staff') || \Laratrust::hasRole('staff');
    
        if ($isSuper || $isCompany) {
            return $tickets;
        }
    
    if ($isStaff) {
        $customerIds = \DB::table('customer_clients_users as ccu')
            ->join('customer_clients as cc', 'cc.id', '=', 'ccu.customer_client_id')
            ->where('ccu.user_id', $user->id)
            ->pluck('cc.customer_id')
            ->unique()
            ->values()
            ->all();
    
        $customerClientIds = \DB::table('customer_clients_users')
            ->where('user_id', $user->id)
            ->pluck('customer_client_id')
            ->unique()
            ->values()
            ->all();
    
        $projectIds = \DB::table('user_projects')
            ->join('projects', 'projects.id', '=', 'user_projects.project_id')
            ->where('user_projects.user_id', '=', $user->id)
            ->where('projects.workspace', '=', $workspaceId)
            ->where('projects.type', '=', 'project')
            ->pluck('projects.id')
            ->unique()
            ->values()
            ->all();
    
        if (empty($customerIds) && empty($customerClientIds) && empty($projectIds)) {
            $tickets->whereRaw('1 = 0');
            return $tickets;
        }
    
        $tickets->where(function ($q) use ($customerIds, $customerClientIds, $projectIds) {
            if (!empty($customerIds)) {
                $q->orWhereIn('helpdesk_tickets.customer_id', $customerIds);
            }
    
            if (!empty($customerClientIds)) {
                $q->orWhereIn('helpdesk_tickets.customer_client_id', $customerClientIds);
            }
    
            if (!empty($projectIds)) {
                $q->orWhereExists(function ($sq) use ($projectIds) {
                    $sq->from('tasks as tk')
                       ->whereColumn('tk.id', 'helpdesk_tickets.task_id')
                       ->whereIn('tk.project_id', $projectIds);
                });
            }
        });
    
        return $tickets;
    }
    
        $tickets->where(function ($q) use ($user) {
            $q->where('helpdesk_tickets.user_id', $user->id)
              ->orWhere('helpdesk_tickets.created_by', $user->id)
              ->orWhereRaw("
                    FIND_IN_SET(
                        ?,
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(COALESCE(helpdesk_tickets.visible_to_user_ids, ''), '\"', ''),
                                '[', ''),
                            ']', ''),
                        ' ', '')
                    ) > 0
                ", [$user->id])
              ->orWhereRaw("
                    FIND_IN_SET(
                        ?,
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(COALESCE(helpdesk_tickets.touched_by_user_ids, ''), '\"', ''),
                                '[', ''),
                            ']', ''),
                        ' ', '')
                    ) > 0
                ", [$user->id])
              ->orWhereExists(function ($sq) use ($user) {
                  $sq->from('helpdesk_ticket_audits as hta')
                     ->whereColumn('hta.ticket_id', 'helpdesk_tickets.id')
                     ->where('hta.changed_by', $user->id);
              });
        });
    
        return $tickets;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId('supports-table')
            ->columns($this->getColumns())
            ->pageLength(25)
            ->ajax([
                'data' => 'function(d) {
                    d.statuses     = Array.from(window.__ticketStatuses || []);
                    d.assigned_to  = $("#filter_assigned_to").val() || [];
                    d.category_id  = $("#filter_category").val() || [];
                     d.date_from    = window.__dateFrom || "";   // si usas el dropdown
                      d.date_to      = window.__dateTo   || "";
                  }',
            ])
            ->orderBy(6, '')
            ->language([
                "paginate" => [
                    "next" => '<i class="ti ti-chevron-right"></i>',
                    "previous" => '<i class="ti ti-chevron-left"></i>'
                ],
                'lengthMenu' => "_MENU_" . __('Entries Per Page'),
                "searchPlaceholder" => __('Search...'),
                "search" => "",
                "info" => __('Showing _START_ to _END_ of _TOTAL_ entries')
            ])
            ->initComplete('function() {
                        var table = this;
                        $("body").on("change", "#projects", function() {
                            $("#supports-table").DataTable().draw();
                        });
                        var searchInput = $(\'#\'+table.api().table().container().id+\' label input[type="search"]\');
                        searchInput.removeClass(\'form-control form-control-sm\');
                        searchInput.addClass(\'dataTable-input\');
                        var select = $(table.api().table().container()).find(".dataTables_length select").removeClass(\'custom-select custom-select-sm form-control form-control-sm\').addClass(\'dataTable-selector\');
                    }');
        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Export"></i>',
            'buttons' => [
                [
                    'extend' => 'print',
                    'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'csv',
                    'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'excel',
                    'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
            ],
        ];

        $buttonsConfig = array_merge([
            $exportButtonConfig,
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ]);

        $dataTable->parameters([
            "dom" =>  "
        <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex justify-content-end gap-2'Bf>>
        <'dataTable-container'<'col-sm-12'tr>>
        <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "drawCallback" => 'function() {
                  var api = this.api();
                  // asegurar centrado si algún plugin toca clases
                  api.columns().every(function(){
                    var header = $(this.header());
                    if (header.hasClass("dt-center")) {
                      header.addClass("dt-center");
                    }
                  });
              }'
        ]);

        $dataTable->language([
            'buttons' => [
                'create' => __('Create'),
                'export' => __('Export'),
                'print' => __('Print'),
                'reset' => __('Reset'),
                'reload' => __('Reload'),
                'excel' => __('Excel'),
                'csv' => __('CSV'),
            ]
        ]);

        return $dataTable;
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->searchable(false)->visible(false)->exportable(false)->printable(false),
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false)->visible(false),
            Column::make('ticket_id')
            ->title(__('Ticket ID'))
            ->addClass('col-ticket-id')
            ->width(120)
            ->addClass('col-ticket dt-center fit-by-max'),
            //Column::make('subject')->title(__('Subject')),
Column::make('subject')
    ->title(__('Subject'))
    ->addClass('col-subject')
    ->width(550),            
            Column::make('assigned_to')->title(__('Assigned To'))->name('au.name')->addClass('fit-by-max'),
            Column::make('category')->title(__('Category'))->addClass('fit-by-max dt-center'),
            Column::make('status')->title(__('Status'))->name('status')->addClass('fit-by-max dt-center'),
            Column::make('priority')->title(__('Priority'))->orderable(true)->searchable(false)->addClass('fit-by-max dt-center'),
            Column::make('created_at')->title(__('Created'))->addClass('fit-by-max dt-center'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)

        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Support_' . date('YmdHis');
    }
}
