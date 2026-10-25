@php
  $isCustomerLike = \Laratrust::hasRole(['Client','Customer','client','customer']);
@endphp
@extends('layouts.main')

@section('page-title')
    {{ __('Dashboard') }}
@endsection

@section('page-breadcrumb')
    {{ __('Project')}}
@endsection

@section('content')
<div class="row row-gap mb-4">
  {{-- KPI LEFT --}}
  <div class="col-xl-6 col-12">
    <div class="dashboard-card">
      <img src="{{ asset('assets/images/layer.png') }}" class="dashboard-card-layer" alt="layer">
      <div class="card-inner">
        <div class="card-content">
          <h2>{{ Auth::user()->ActiveWorkspaceName() }}</h2>
          <p>{{ __('Optimizes project management with task tracking, timelines, and real-time progress updates.') }}</p>
        </div>
        <div class="card-icon d-flex align-items-center justify-content-center"></div>
      </div>
    </div>
  </div>

  {{-- KPI RIGHT --}}
  <div class="col-xl-6 col-12">
    <div class="row dashboard-wrp">

      {{-- TOTAL PROJECT (clickable) --}}
      <div class="col-sm-6 col-12">
        <a href="{{ route('projects.index') }}" class="text-decoration-none d-block"> {{-- <-- NUEVO --}}
          <div class="dashboard-project-card">
            <div class="card-inner d-flex justify-content-between">
              <div class="card-content">
                <div class="theme-avtar bg-white">
                  <i class="fas fa-tasks text-danger"></i>
                </div>
                <h3 class="mt-3 mb-0 text-danger">{{ __('Total Projects') }}</h3>
              </div>
              <h3 class="mb-0">{{ $totalProject }}</h3>
            </div>
          </div>
        </a>
      </div>

      {{-- TOTAL TASKS --}}
      <div class="col-sm-6 col-12">
        @php
          // Para Client/Customer, mostramos tareas NO terminadas (punto #1)
          $kpiTasksCount = $isCustomerLike ? ($totalTaskNotDone ?? 0) : ($totalTask ?? 0);
          // Mejor destino al hacer click:
          $tasksUrl = \Illuminate\Support\Facades\Route::has('projects.index')
            ? route('projects.index')
            : 'javascript:void(0);';
        @endphp
        <a href="{{ $tasksUrl }}" class="text-decoration-none d-block"> {{-- <-- NUEVO --}}
          <div class="dashboard-project-card">
            <div class="card-inner d-flex justify-content-between">
              <div class="card-content">
                <div class="theme-avtar bg-white">
                  <i class="ti ti-file-invoice"></i>
                </div>
                <h3 class="mt-3 mb-0">
                   {{  __('Open Tasks') }}
                </h3>
              </div>
              <h3 class="mb-0">{{ $kpiTasksCount }}</h3>
            </div>
          </div>
        </a>
      </div>

      {{-- TOTAL TICKETS --}}
      <div class="col-sm-6 col-12">
        @php
          $ticketsUrl = \Illuminate\Support\Facades\Route::has('helpdesk-ticket.index')
            ? route('helpdesk-ticket.index')
            : ( \Illuminate\Support\Facades\Route::has('helpdesk.index') ? route('helpdesk.index') : 'javascript:void(0);' );

          // Para Client/Customer mostramos "sin resolver donde estoy incluido"
          $kpiTicketsCount = $isCustomerLike
            ? ($totalTicketAssigned ?? 0)
            : ($totalTicketAssigned ?? 0);
        @endphp
        <a href="{{ $ticketsUrl }}" class="text-decoration-none d-block"> {{-- <-- NUEVO --}}
          <div class="dashboard-project-card">
            <div class="card-inner d-flex justify-content-between">
              <div class="card-content">
                <div class="theme-avtar bg-white">
                  <i class="ti ti-ticket fs-2"></i>
                </div>
                <h3 class="mt-3 mb-0">
                  {{ __('Unresolved Tickets') }}
                </h3>
              </div>
              <h3 class="mb-0">{{ $kpiTicketsCount }}</h3>
            </div>
          </div>
        </a>
      </div>

      {{-- TIMESHEETS POR APROBAR --}}
        @if(!empty($canViewTimesheets))
            {{-- Client/Customer: Timesheets por aprobar --}}
            @php
              $timesheetsUrl = \Illuminate\Support\Facades\Route::has('timesheet.index')
                ? route('timesheet.index')
                : 'javascript:void(0);';
            @endphp
            <div class="col-sm-6 col-12">
              <a href="{{ $timesheetsUrl }}" class="text-decoration-none d-block"> {{-- <-- NUEVO --}}
                <div class="dashboard-project-card">
                  <div class="card-inner d-flex justify-content-between">
                    <div class="card-content">
                      <div class="theme-avtar bg-white">
                        <i class="ti ti-checkbox"></i>
                      </div>
                      <h3 class="mt-3 mb-0">{{ __('Timesheets to Approve') }}</h3>
                    </div>
                    <h3 class="mb-0">{{ $totalTimesheetsToApprove ?? 0 }}</h3>
                  </div>
                </div>
              </a>
            </div>
        @endif
    </div>
  </div>
</div>

{{-- ROW: LEFT (Tasks + Tickets) / RIGHT (Charts) --}}
<div class="row">
  <div class="col-xxl-7">

    {{-- TASKS --}}
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">{{ __('Tasks') }}</h5>
          <div class="float-end">
            <small><b>{{ $totaltasks }}</b> {{ __('Undone tasks') }}</small>
          </div>
        </div>
      </div>

      <div class="card-body">
        @php
            $hasTasks = isset($tasks) && $tasks->count() > 0;
        @endphp
        
        <div class="table-responsive {{ $hasTasks ? 'custom-scrollbar' : '' }}"
             style="{{ $hasTasks ? 'max-height:515px;overflow-y:auto;' : '' }}">
          <table class="table table-centered table-hover mb-0 animated">
            <tbody>
              @if(isset($tasks) && $tasks->count() > 0)
                @foreach($tasks as $task)
                  <tr>
                    <td>
                      <div class="font-14 my-1">
                        <a href="{{ route('projects.task.board', [$task->project_id]) }}" class="text-body">{{ $task->title }}</a>
                      </div>
                      @php
                        $isLate = ($task->due_date < date('Y-m-d'));
                        $due = '<span class="text-' . ($isLate ? 'danger' : 'success') . '">' . date('Y-m-d', strtotime($task->due_date)) . '</span>';
                      @endphp
                      <span class="text-muted font-13">{{ __('Due Date') }} : {!! $due !!}</span>
                    </td>
                    <td>
                      <span class="text-muted font-13">{{ __('Status') }}</span><br>
                      @if ($task->complete == '1')
                        <span class="badge bg-success p-2 px-3">{{ __($task->status) }}</span>
                      @else
                        <span class="badge bg-primary p-2 px-3">{{ __($task->status) }}</span>
                      @endif
                    </td>
                    <td>
                      <span class="text-muted font-13">{{ __('Project') }}</span>
                      <div class="font-14 mt-1 font-weight-normal">{{ $task->project->name }}</div>
                    </td>
                    @if (Auth::user()->hasRole('client'))
                      <td>
                        <span class="text-muted font-13">{{ __('Assigned to') }}</span>
                        <div class="font-14 mt-1 font-weight-normal">
                          @foreach ($task->users() as $user)
                            <span class="badge p-2 px-2 bg-secondary">{{ $user->name ?? '-' }}</span>
                          @endforeach
                        </div>
                      </td>
                    @endif
                  </tr>
                @endforeach
              @else
                <tr>
                  <td class="text-muted text-center">{{ __('No tasks found.') }}</td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- MY TICKETS --}}
    <div class="card shadow-sm border-0 mt-4" id="tickets-my-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
          {{ $isCustomerLike ? __('Unresolved Tickets') : __('Unresolved Tickets') }}
        </h6>
        <span class="badge bg-primary">{{ ($myTickets ?? collect())->count() }}</span>
      </div>

      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          @forelse(($myTickets ?? collect()) as $t)
            @php
                $encTid = \Illuminate\Support\Facades\Crypt::encrypt($t->ticket_id);
            
                $isAssignedToMe = auth()->id() && ((int) ($t->user_id ?? 0) === (int) auth()->id());
            
                $canEdit =
                    auth()->check() &&
                    \Laratrust::hasPermission('helpdesk ticket show') &&
                    (
                        auth()->user()->type == 'super admin' ||
                        auth()->user()->type == 'company' ||
                        $isAssignedToMe
                    );
            
                if ($isAssignedToMe && $canEdit) {
                    $url = route('helpdesk.edit', $t->id);
                    $label = 'Open';
                } else {
                    $url = route('helpdesk.view', [$encTid]);
                    $label = 'View';
                }
            @endphp
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div class="me-3">
                <a href="{{ $url }}" class="fw-semibold text-decoration-none">
                  #{{ $t->ticket_id }} — {{ \Illuminate\Support\Str::limit($t->subject, 70) }}
                </a>
                <div class="small text-muted mt-1">
                  {{ __('Status') }}: <span class="fw-semibold">{{ $t->status }}</span>
                
                  @if(!empty($t->priority))
                    • {{ __('Priority') }}: {{ $t->priority }}
                  @endif
                
                  @if(!empty($t->created_at))
                    • {{ __('Created') }}: {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}
                  @endif
                  
                 @if($isAssignedToMe)
                   • <span class="fw-semibold text-success">{{ __('Assigned to you') }}</span>
                 @endif                  
                </div>
              </div>
              <div>
                <a class="btn btn-sm btn-outline-primary" href="{{ $url }}">{{ $label }}</a>
              </div>
            </li>
          @empty
            <li class="list-group-item text-muted">{{ __('No tickets assigned to you for this project.') }}</li>
          @endforelse
        </ul>
      </div>
    </div>

  </div>

  {{-- RIGHT COLUMN (Charts) --}}
  <div class="col-xxl-5">
    <div class="card">
      <div class="card-header"><h5>{{ __('Tasks Overview') }}</h5></div>
      <div class="card-body p-2"><div id="task-area-chart"></div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="float-end"><a href="#" data-bs-toggle="tooltip" title="Refferals"><i class=""></i></a></div>
        <h5>{{ __('Project Status') }}</h5>
      </div>
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-sm-8"><div id="projects-chart"></div></div>
          <div class="col-sm-4">
            <div class="col-6"><span class="d-flex align-items-center mb-2"><i class="f-10 lh-1 fas fa-circle text-danger"></i><span class="ms-2 text-sm">{{ __('On Going') }}</span></span></div>
            <div class="col-6"><span class="d-flex align-items-center mb-2"><i class="f-10 lh-1 fas fa-circle text-warning"></i><span class="ms-2 text-sm">{{ __('On Hold') }}</span></span></div>
            <div class="col-6"><span class="d-flex align-items-center mb-2"><i class="f-10 lh-1 fas fa-circle text-primary"></i><span class="ms-2 text-sm">{{ __('Finished') }}</span></span></div>
          </div>
          <div class="row text-center">
            @foreach ($arrProcessPer as $index => $value)
              <div class="col-4">
                <i class="fas fa-chart"></i>
                <h6 class="font-weight-bold"><span>{{ $value }}%</span></h6>
                <p class="text-muted">{{ __($arrProcessLabel[$index]) }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
(function(){
  var options = {
    chart: { height: 170, type: 'donut' },
    dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '70%' } } },
    series: {!! json_encode($arrProcessPer) !!},
    colors: ['#FF3A6E', '#6fd943', '#ffa21d'],
    labels: {!! json_encode($arrProcessLabel) !!},
    grid: { borderColor: '#e7e7e7', row: { colors: ['#f3f3f3', 'transparent'], opacity: 0.5 } },
    markers: { size: 1 },
    legend: { show: false }
  };
  var chart = new ApexCharts(document.querySelector("#projects-chart"), options);
  chart.render();
})();
</script>

<script>
(function(){
  var options = {
    chart: { height: 157, type: 'line', toolbar: { show: false } },
    dataLabels: { enabled: false },
    stroke: { width: 2, curve: 'smooth' },
    series: [
      @foreach ($chartData['stages'] as $id => $name)
      { name: "{{ __($name) }}", data: {!! json_encode($chartData[$id]) !!} },
      @endforeach
    ],
    xaxis: { categories: {!! json_encode($chartData['label']) !!} },
    colors: {!! json_encode($chartData['color']) !!},
    grid: { strokeDashArray: 4 },
    legend: { show: false },
    yaxis: { tickAmount: 5, min: 1, max: 40 }
  };
  var chart = new ApexCharts(document.querySelector("#task-area-chart"), options);
  chart.render();
})();
</script>
@endpush
