<?php

namespace Workdo\Timesheet\DataTables;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Workdo\Timesheet\Entities\Timesheet;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\DB;

class TimesheetDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['bulk', 'user_name', 'project_id', 'customer_client_id', 'validated', 'billed', 'paid'];
        $user      = Auth::user();
        $isCustomerLike  = \Laratrust::hasRole(['Customer','Client']);
        $isVendorConsultant  = $user->hasRole('Vendor') || $user->hasRole('Consultant');
        $isSuper = $user && ($user->hasRole('super admin') || $user->hasRole('Company'));
        $canInvoice = $isSuper || \Laratrust::hasPermission([
            'invoice manage','invoice view','invoice show',
            'invoice create','invoice edit','invoice delete'
        ]);
        $canBilled = !(optional($user)->hasRole(['Customer','Client']) ?? false);         
        $dataTable = (new EloquentDataTable($query))
            ->addIndexColumn()

            // Checkbox por fila
            ->addColumn('bulk', function (Timesheet $row) {
                return sprintf(
                    '<div class="text-center">
                       <input type="checkbox" class="ts-bulk"
                              value="%d"
                              data-validated="%d"
                              data-billed="%d"
                              data-invoiced="%d"
                              data-paid="%d">
                     </div>',
                    $row->id,
                    (int) $row->validated,
                    (int) $row->billed,
                    (int) ($row->invoiced ?? 0),
                    (int) $row->paid
                );
            })
            ->addColumn('total', function (Timesheet $m) {
                return sprintf('%02d:%02d', (int)$m->hours, (int)$m->minutes);
            })
            ->editColumn('user_name', function (Timesheet $model) {
                return $model->user_name ?? '-';
            })
            ->editColumn('project_id', function (Timesheet $model) {
                return $model->project_name ?? '-';
            })
            ->editColumn('customer_client_id', function (Timesheet $model) {
                return $model->client_name ?? '-';
            })
            ->addColumn('validated', function (Timesheet $m) {
                $checked = $m->validated ? 'checked' : '';
                return '<div class="text-center"><input type="checkbox" class="form-check-input m-0 ts-readonly" '.$checked.'></div>';
            });
            
            
            if ($canBilled || $isVendorConsultant || $isSuper) {
                $dataTable->addColumn('billed', function (Timesheet $m) {
                    $checked = $m->billed ? 'checked' : '';
                    return '<div class="text-center"><input type="checkbox" class="form-check-input m-0 ts-readonly" '.$checked.'></div>';
                })
                ->addColumn('paid', function (Timesheet $m) {
                    $checked = $m->paid ? 'checked' : '';
                    return '<div class="text-center"><input type="checkbox" class="form-check-input m-0 ts-readonly" '.$checked.'></div>';
                });
            }
            if ($canInvoice || $isCustomerLike || $isSuper) {
                $dataTable->addColumn('invoiced', function (Timesheet $m) {
                    $checked = $m->invoiced ? 'checked' : '';
                    return '<div class="text-center"><input type="checkbox" class="form-check-input m-0 ts-readonly" '.$checked.'></div>';
                });
                $rowColumn[] = 'invoiced';
            }
            
            $wsId = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;

            $canEditView = \Laratrust::hasPermission(['timesheet edit','timesheet view'])
                        || (\Laratrust::hasPermission(['timesheet edit','timesheet view'], $wsId));
            
            $isSuperTop = \Laratrust::hasRole('super admin')
                       || (\Laratrust::hasRole('super admin', $wsId))
                       || \Laratrust::hasRole('Company')
                       || (\Laratrust::hasRole('Company', $wsId));

            if ($canEditView || $isSuperTop || $isCustomerLike) {
                $dataTable->addColumn('action', function (Timesheet $t) {
                    // Re-read auth each row to avoid closure-stale values
                    $auth   = \Illuminate\Support\Facades\Auth::user();
                    $authId = (int) \Illuminate\Support\Facades\Auth::id();
            
                    $wsId   = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;                    
                    $isSuper = false;
                    $isCompany = $auth->hasRole('Company');
                    // 1) Muchas instalaciones Dash guardan 'type' = 'super admin'
                    if (isset($auth->type) && (strtolower($auth->type) === 'super admin' || $isCompany)) {
                        $isSuper = true;
                    }
            
                    // 2) Laratrust: global y team/workspace
                    if (!$isSuper) {
                        $isSuper = \Laratrust::hasRole('super admin')
                                || ($wsId && \Laratrust::hasRole('super admin', $wsId))
                                || (method_exists($auth,'hasRole') && (
                                        $auth->hasRole('super admin')
                                     || ($wsId && $auth->hasRole('super admin', $wsId))))
                                ||     \Laratrust::hasRole('Company')
                                || ($wsId && \Laratrust::hasRole('Company', $wsId))
                                || (method_exists($auth,'hasRole') && (
                                        $auth->hasRole('Company')
                                     || ($wsId && $auth->hasRole('Company', $wsId))
                                   ));
                    }
                                 
                    // Cast to int (or use loose ==) AND compare created_by to tenant owner
                    $isOwner = ((int) $t->user_id === $authId);
            
                    $editUrl = route('timesheet.edit', $t->id);
                    $viewUrl = route('timesheet.view', $t->id);
		            $deleteUrl = route('timesheet.destroy', $t->id);          
		    
                    if ($isOwner || $isSuper) {
                        $btns  = '<div class="text-center">';
                        $canEdit = !$t->validated && (!$isCompany || $isOwner);
                        // EDIT EN MODAL (mismo contenedor que Create)
                        if($canEdit)
                        {
                            $btns .= '
                                <a href="#"
                                   class="btn btn-sm btn-primary"
                                   data-ajax-popup="true"
                                   data-size="lg"
                                   data-title="'.e(__('Edit Timesheet')).'"
                                   data-url="'.$editUrl.'?popup=1"
                                   data-bs-toggle="tooltip"
                                   title="'.e(__('Edit Timesheet')).'">
                                  <i class="ti ti-pencil"></i>
                                </a>';
                        }   
                        // VIEW (sin cambios)
                            $btns .= '
                                <a href="#"
                                   class="btn btn-sm btn-secondary ms-1"
                                   data-ajax-popup="true"
                                   data-size="lg"
                                   data-title="'.e(__('View Timesheet')).'"
                                   data-url="'.$viewUrl.'?popup=1"
                                   data-bs-toggle="tooltip"
                                   title="'.e(__('View Timesheet')).'">
                                  <i class="ti ti-eye"></i>
                                </a>';                        
                    
                        // DELETE (sin cambios)
                        if($canEdit)
                        {
                            $btns .= '
                                <form id="delete-form-'.$t->id.'" action="'.$deleteUrl.'" method="POST" class="d-inline">
                                  '.csrf_field().method_field('DELETE').'
                                  <button type="button"
                                          class="btn btn-sm ms-1 bg-danger text-white show_confirm bs-pass-para"
                                          data-confirm="'.e(__('Are You Sure?')).'"
                                          data-text="'.e(__('This action can not be undone. Do you want to continue?')).'"
                                          data-confirm-yes="delete-form-'.$t->id.'"
                                          data-bs-toggle="tooltip"
                                          title="'.e(__('Delete')).'">
                                    <i class="ti ti-trash"></i>
                                  </button>
                                </form>';
                        }
                        $btns .= '</div>';
                        return $btns;
                    }
                    return '<div class="text-center" data-debug="'.$isSuper.'">                    
                                <a href="#"
                                   class="btn btn-sm btn-secondary ms-1"
                                   data-ajax-popup="true"
                                   data-size="lg"
                                   data-title="'.e(__('View Timesheet')).'"
                                   data-url="'.$viewUrl.'?popup=1"
                                   data-bs-toggle="tooltip"
                                   title="'.e(__('View Timesheet')).'">
                                  <i class="ti ti-eye"></i>
                                </a>
                            </div>';
                });
                $rowColumn[] = 'action';
            }
        return $dataTable->rawColumns($rowColumn);
    }

    public function query(Timesheet $model): QueryBuilder
    {
        $from = request('date_from') ?: now()->startOfMonth()->toDateString();
        $to   = request('date_to')   ?: now()->endOfMonth()->toDateString();
        $status = request('status');
    
        $q = $model->newQuery()
            ->select(
                'timesheets.*',
                'users.name as user_name',
                'projects.name as project_name',
                'customer_clients.name as client_name'
            )
            ->leftJoin('users', 'timesheets.user_id', '=', 'users.id')
            ->leftJoin('customer_clients', 'timesheets.customer_client_id', '=', 'customer_clients.id')
            ->leftJoin('projects', 'timesheets.project_id', '=', 'projects.id')
            ->whereBetween('timesheets.date', [$from, $to]);
    
        $user   = Auth::user();
        $userId = (int) $user->id;
        $type   = strtolower(trim((string) ($user->type ?? '')));
    
        $canSeeAllTimesheets =
            \Laratrust::hasRole('super admin')
            || \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || \Laratrust::hasRole('staff')
            || \Laratrust::hasRole('Staff')
            || $type === 'staff';
    
        $isCustomerLike = \Laratrust::hasRole(['Customer','Client']);
    
        $canManageTs = $user->isAbleTo('timesheet validate')
                    || $user->isAbleTo('timesheet unvalidate');
    
        if (\Schema::hasColumn('timesheets', 'workspace_id')) {
            $q->where('timesheets.workspace_id', getActiveWorkSpace());
        }
    
        if (\Schema::hasColumn('timesheets', 'created_by')) {
            $q->where('timesheets.created_by', creatorId());
        }
    
        $allowedProjectIds = [];
        $allowedClientIds  = [];
    
        if ($isCustomerLike) {
            $isClient = \Laratrust::hasRole('Client');
    
            $projectIdsAsClient = DB::table('client_projects')
                ->where('client_id', $userId)
                ->pluck('project_id');
    
            $projectIdsAsMember = DB::table('user_projects')
                ->where('user_id', $userId)
                ->pluck('project_id');
    
            $allowedProjectIds = $projectIdsAsClient->merge($projectIdsAsMember)->unique()->all();
    
            $customerIds = DB::table('customer_users')
                ->where('user_id', $userId)
                ->pluck('customer_id');
    
            $ccIdsDirect = DB::table('customer_clients_users')
                ->where('user_id', $userId)
                ->pluck('customer_client_id');
    
            $ccIdsViaCustomer = $customerIds->isNotEmpty()
                ? DB::table('customer_clients')->whereIn('customer_id', $customerIds)->pluck('id')
                : collect();
    
            $allowedClientIds = $isClient
                ? $ccIdsDirect->merge($ccIdsViaCustomer)->unique()->all()
                : $ccIdsDirect->unique()->all();
    
            if (empty($allowedProjectIds) && empty($allowedClientIds)) {
                $q->whereRaw('0=1');
            } else {
                $q->where(function($w) use ($allowedProjectIds, $allowedClientIds) {
                    if (!empty($allowedProjectIds)) {
                        $w->orWhereIn('timesheets.project_id', $allowedProjectIds);
                    }
                    if (!empty($allowedClientIds)) {
                        $w->orWhereIn('timesheets.customer_client_id', $allowedClientIds);
                    }
                });
            }
        } else {
            // Regla principal:
            // solo super admin / company / staff / type staff pueden ver más allá de sí mismos
            if (!$canSeeAllTimesheets) {
                $q->where('timesheets.user_id', $userId);
            }
        }
    
        // ===== Filters =====
    
        $clientIds = array_filter((array) request('client_ids', []));
        if (!empty($clientIds)) {
            if ($isCustomerLike) {
                $clientIds = array_values(array_intersect($clientIds, $allowedClientIds));
                if (empty($clientIds)) {
                    $q->whereRaw('0=1');
                }
            }
            if (!empty($clientIds)) {
                $q->whereIn('timesheets.customer_client_id', $clientIds);
            }
        }
    
        $consultantIds = array_filter((array) request('consultant_ids', []));
    
        if (!empty($consultantIds)) {
            if ($isCustomerLike) {
                $visibleConsultantIds = collect();
    
                if (!empty($allowedProjectIds)) {
                    $visibleConsultantIds = $visibleConsultantIds->merge(
                        DB::table('user_projects')->whereIn('project_id', $allowedProjectIds)->pluck('user_id')
                    );
                }
    
                if (!empty($allowedProjectIds) || !empty($allowedClientIds)) {
                    $tsUsers = DB::table('timesheets')
                        ->where('created_by', creatorId())
                        ->where('workspace_id', getActiveWorkSpace())
                        ->where(function ($qq) use ($allowedProjectIds, $allowedClientIds) {
                            if (!empty($allowedProjectIds)) {
                                $qq->whereIn('project_id', $allowedProjectIds);
                            }
                            if (!empty($allowedClientIds)) {
                                $qq->orWhereIn('customer_client_id', $allowedClientIds);
                            }
                        })
                        ->pluck('user_id');
    
                    $visibleConsultantIds = $visibleConsultantIds->merge($tsUsers);
                }
    
                $visibleConsultantIds = $visibleConsultantIds->unique()->all();
                $consultantIds = array_values(array_intersect($consultantIds, $visibleConsultantIds));
    
                if (empty($consultantIds)) {
                    $q->whereRaw('0=1');
                }
            } else {
                // Si no puede ver todo, no puede filtrar por otros empleados
                if (!$canSeeAllTimesheets) {
                    $consultantIds = [$userId];
                }
            }
    
            if (!empty($consultantIds)) {
                $q->whereIn('timesheets.user_id', $consultantIds);
            }
        } else {
            // Sin filtro explícito, si no puede ver todo, queda obligado a verse a sí mismo
            if (!$isCustomerLike && !$canSeeAllTimesheets) {
                $q->where('timesheets.user_id', $userId);
            }
        }
    
        if ($status && $status !== 'All') {
            switch ($status) {
                case 'Unapproved':
                    $q->where('timesheets.validated', false);
                    break;
                case 'Approved':
                    $q->where('timesheets.validated', true);
                    break;
                case 'Billed':
                    $q->where('timesheets.billed', true);
                    break;
                case 'Paid':
                    $q->where('timesheets.paid', true);
                    break;
                case 'Invoiced':
                    $q->where('timesheets.invoiced', true);
                    break;
            }
        }
    
        return $q;
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->builder()
            ->setTableId('timesheet-table')
            ->columns($this->getColumns())
            ->pageLength(50)
            ->ajax([
                'url' => route('timesheet.index'),
                'type' => 'GET', 
                'data' => 'function(d){
                                d.date_from = $("#filter-date-from").val();
                                d.date_to = $("#filter-date-to").val();
                            }',
                ])
            ->orderBy(0)
            ->parameters([
                'responsive' => true,
                'autoWidth'  => false,
            ])
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
                var searchInput = $(\'#\'+table.api().table().container().id+\' label input[type="search"]\');
                searchInput.removeClass(\'form-control form-control-sm\');
                searchInput.addClass(\'dataTable-input\');
                $(table.api().table().container())
                    .find(".dataTables_length select")
                    .removeClass(\'custom-select custom-select-sm form-control form-control-sm\')
                    .addClass(\'dataTable-selector\');
            }');


        // Botones existentes
        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Export"></i>',
            'buttons' => [
                [
                    'extend' => 'print',
                    'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => '.export-col'],
                ],
                [
                    'extend' => 'csv',
                    'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => '.export-col'],
                ],
                [
                    'extend' => 'excel',
                    'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => '.export-col'],
                ],
            ],
        ];

        $buttonsConfig = [
            $exportButtonConfig,
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ];

        $builder->parameters([
            "dom" =>  "
                <'dataTable-top'
                    <'dataTable-dropdown page-dropdown'l>
                    <'dataTable-botton table-btn dataTable-search tb-search d-flex justify-content-end gap-2'Bf>
                >
                <'dataTable-container'<'col-sm-12'tr>>
                <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "drawCallback" => 'function(settings) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=tooltip]"));
                tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
                document.querySelectorAll("[data-bs-toggle=tooltip]").forEach(function(el){
                      if (!bootstrap.Tooltip.getInstance(el)) new bootstrap.Tooltip(el, {container:"body"});
                    });        
                $("#ts-bulk-all").prop("checked", false);
                $(".ts-bulk").prop("checked", false);
        
                $("#btn-validate,#btn-unvalidate").prop("disabled", true);
            }',
            'orderMulti' => false
        ]);

        $builder->language([
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

        return $builder;
    }

    public function getColumns(): array
    {
        $columns = [
            // PK interno (no export, no print, no display)
            Column::make('id')
                ->searchable(false)
                ->visible(false)
                ->exportable(false)
                ->printable(false),
    
            // Index (No) (no export, no print, hidden)
            Column::make('No')->title(__('No'))
                ->data('DT_RowIndex')->name('DT_RowIndex')
                ->searchable(false)->orderable(false)
                ->visible(false)
                ->exportable(false)->printable(false),
    
            // Checkbox header (select all) (no export, no print)
            Column::computed('bulk')
                ->title('<div class="text-center">
                           <input type="checkbox" id="ts-bulk-all"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="bottom"
                                  data-bs-trigger="hover"
                                  title="Select All">
                         </div>')
                ->exportable(false)->printable(false)
                ->orderable(false)->searchable(false)
                ->width(30),
    
            // ====== Exportables (marcadas con export-col) ======
            Column::make('user_name')
                ->title(__('Employee'))
                ->name('users.name')
                ->addClass('export-col'),
    
            Column::make('project_id')
                ->title(__('Project'))
                ->name('projects.name')
                ->addClass('export-col'),
    
            Column::make('customer_client_id')
                ->title(__('Client'))
                ->name('customer_clients.name')
                ->addClass('export-col'),
    
            Column::make('date')
                ->title(__('Date'))
                ->addClass('text-center export-col'),
    
            Column::make('total')
                ->title(__('Total'))
                ->addClass('text-center export-col'),
    
            // ====== NUEVO: Description (NO visible, SÍ exportable) ======
            Column::make('description')
                ->title(__('Description'))
                ->visible(false)         // <- no se muestra en UI
                ->exportable(true)
                ->printable(true)
                ->addClass('export-col'),
    
            // Approved (no export, sí printable si lo quieres)
            Column::computed('validated')
                ->title(__('Approved'))
                ->exportable(false)
                ->printable(true)
                ->orderable(false)
                ->searchable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    
        $isCustomerLike = \Laratrust::hasRole(['Customer','Client']);
    
        $canInvoice = \Laratrust::hasPermission('invoice manage')
            || \Laratrust::hasPermission('invoice view')
            || \Laratrust::hasPermission('invoice show')
            || \Laratrust::hasPermission('invoice create')
            || \Laratrust::hasPermission('invoice edit')
            || \Laratrust::hasPermission('invoice delete');
    
        $wsId = function_exists('getActiveWorkSpace') ? getActiveWorkSpace() : null;
        $isSuperTop = \Laratrust::hasRole('super admin')
            || ($wsId && \Laratrust::hasRole('super admin', $wsId))
            || \Laratrust::hasRole('Company')
            || ($wsId && \Laratrust::hasRole('Company', $wsId));
    
        // Billed/Paid solo para no-customer
        if (!$isCustomerLike) {
            $columns[] = Column::computed('billed')
                ->title(__('Billed'))
                ->exportable(false)
                ->printable(true)
                ->orderable(false)
                ->searchable(false)
                ->width(60)
                ->addClass('text-center');
    
            $columns[] = Column::computed('paid')
                ->title(__('Paid'))
                ->exportable(false)
                ->printable(true)
                ->orderable(false)
                ->searchable(false)
                ->width(60)
                ->addClass('text-center');
        }
    
        // Invoiced (condicional)
        if (($canInvoice && $isCustomerLike) || $isSuperTop) {
            $columns[] = Column::computed('invoiced')
                ->title(__('Invoiced'))
                ->exportable(false)
                ->printable(true)
                ->orderable(false)
                ->searchable(false)
                ->width(60)
                ->addClass('text-center');
        }
    
        // Action (no export, no print)
        $canEditView = \Laratrust::hasPermission(['timesheet edit','timesheet view'])
            || (\Laratrust::hasPermission(['timesheet edit','timesheet view'], $wsId));
    
        if ($canEditView || $isSuperTop || \Laratrust::hasRole(['Customer','Client'])) {
            $columns[] = Column::computed('action')
                ->title(__('Action'))
                ->exportable(false)
                ->printable(false)
                ->width(60);
        }
    
        return $columns;
    }

    protected function filename(): string
    {
        return 'Timesheets' . date('YmdHis');
    }
}
