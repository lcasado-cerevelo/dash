@extends('layouts.main')

@section('page-title')
    {{ __('Manage Tickets') }}
@endsection
@section('page-breadcrumb')
    {{ __('Tickets') }}
@endsection

@push('css')
    @include('layouts.includes.datatable-css')

    {{-- Choices (multi-select como en Timesheet) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>

    <style>
 
      /* DataTables: centrado y sort icon sin desplazar texto */
      table.dataTable thead th.dt-center, table.dataTable thead td.dt-center{ text-align:center!important; }
      table.dataTable tbody th.dt-center, table.dataTable tbody td.dt-center{ text-align:center!important; vertical-align:middle; }
      table.dataTable thead .sorting, table.dataTable thead .sorting_asc, table.dataTable thead .sorting_desc,
      table.dataTable thead .sorting_asc_disabled, table.dataTable thead .sorting_desc_disabled{
        padding-right:0!important; position:relative;
      }
      table.dataTable thead .sorting:before, table.dataTable thead .sorting:after,
      table.dataTable thead .sorting_asc:before, table.dataTable thead .sorting_asc:after,
      table.dataTable thead .sorting_desc:before, table.dataTable thead .sorting_desc:after,
      table.dataTable thead .sorting_asc_disabled:before, table.dataTable thead .sorting_asc_disabled:after,
      table.dataTable thead .sorting_desc_disabled:before, table.dataTable thead .sorting_desc_disabled:after{
        right:.35rem!important; left:auto!important; margin-left:0!important;
      }
      table.dataTable thead th.dt-center{ text-indent:-15px; }
      table.dataTable th.fit-by-max, table.dataTable td.fit-by-max{ box-sizing:content-box; }
        .form-control.multi-select {
          background-color: #fff !important;
          border-radius: 6px !important; /* ajusta el valor (px) a tu gusto */
        }
        
        /* Antes de inicializar (fallback) */
        .form-control.multi-select {
          background:#fff !important;
          border-radius:6px !important;
        }
        
        /* Contenedor principal de Choices */
        .choices { margin-bottom: 0; }
        
        /* Caja visible del control */
        .choices__inner {
          background:#fff !important;
          border-radius:6px !important;
          min-height: 38px; /* ajusta si lo necesitas */
        }
        
        /* En foco / abierto (opcional: estilo tipo Bootstrap) */
        .choices.is-focused .choices__inner,
        .choices.is-open .choices__inner {
          background:#fff !important;
          border-color:#86b7fe;
          box-shadow:0 0 0 .2rem rgba(13,110,253,.25);
        }
        
        /* Dropdown / lista de opciones */
        .choices__list--dropdown,
        .choices__list[aria-expanded] {
          background:#fff !important;
          border-radius:6px !important;
        }
        
        /* Campo de búsqueda dentro del dropdown */
        .choices__input {
          background:#fff !important;
          border-radius:6px !important;
        }
        
        /* Hover de opción (suave) */
        .choices__list--dropdown .choices__item--selectable.is-highlighted {
          background:#f5f5f5;
        }
        
        .date-filter{
            height: 45px;
        }
        
        .helpdesk-table-wrap table.dataTable th,
        .helpdesk-table-wrap table.dataTable td {
          vertical-align: middle;
        }
        
        .helpdesk-table-wrap table.dataTable th.col-subject,
        .helpdesk-table-wrap table.dataTable td.col-subject {
          width: 500px !important;
          min-width: 550px !important;
          max-width: 550px !important;
        }
        
        .subject-cell {
          display: block;
          width: 100%;
          max-width: 100%;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        
        .helpdesk-table-wrap table.dataTable th.col-ticket-id,
        .helpdesk-table-wrap table.dataTable td.col-ticket-id {
          width: 120px !important;
          min-width: 120px !important;
          max-width: 120px !important;
          white-space: nowrap;
        }        
    </style>
@endpush

@section('page-action')
  <div class="row">
    <div class="col-auto ps-3 mt-1">
      @permission('helpdesk ticket create')
        <a href="{{ route('helpdesk.create') }}" class="btn btn-sm btn-primary btn-icon"
           data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Create') }}">
          <i class="ti ti-plus text-white"></i>
        </a>
      @endpermission
    </div>
  </div>
@endsection

@section('content')
@php
  $isSuper     = \Laratrust::hasRole('super admin') || \Laratrust::hasRole('superadministrator');
  $isHelpdesk  = \Laratrust::hasRole('helpdesk') || \Laratrust::hasPermission('helpdesk ticket manage');
  $isConsult   = \Laratrust::hasRole('consultant') || \Laratrust::hasPermission('timesheet manage');
  $showEmpFilt = $isSuper || $isHelpdesk || $isConsult;
@endphp

{{-- ===== FILTROS (misma estructura que Timesheet) ===== --}}
<div class="row mb-3">
  <div class="row d-flex align-items-center justify-content-end">
    @if($showEmpFilt)
      <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12 mr-2">
        <div class="btn-box">
          <label class="form-label">{{ __('Employee(s) Assigned To') }}</label>
          <select id="filter-assigned-to" class="form-control multi-select" multiple="multiple"
                  data-placeholder="{{ __('- Select employee(s) -') }}">
            @foreach($assignees as $u)
              <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    @endif

    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12 mr-2">
      <div class="btn-box">
        <label class="form-label">{{ __('Category') }}</label>
        <select id="filter-category" class="form-control multi-select" multiple="multiple"
                data-placeholder="{{ __('- Select category(ies) -') }}">
          @foreach($categories as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
      <div class="btn-box">
        <label class="form-label">{{ __('Date From') }}</label>
        <input type="date" id="filter-date-from" class="form-control date-filter">
      </div>
    </div>

    <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
      <div class="btn-box">
        <label class="form-label">{{ __('Date To') }}</label>
        <input type="date" id="filter-date-to" class="form-control date-filter">
      </div>
    </div>

    <div class="col-auto mt-4 d-flex align-items-center gap-2">
      <a href="#" id="btn-apply-filters"
         class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center"
         style="width:34px;height:34px;padding:0;" data-bs-trigger="hover"
         data-bs-toggle="tooltip" title="{{ __('Apply') }}">
        <i class="ti ti-search"></i>
      </a>
      <a href="#" id="btn-clear-filters"
         class="btn btn-danger btn-sm d-inline-flex align-items-center justify-content-center"
         style="width:34px;height:34px;padding:0;" data-bs-trigger="hover"
         data-bs-toggle="tooltip" title="{{ __('Clear') }}">
        <i class="ti ti-trash-off"></i>
      </a>
    </div>
  </div>
</div>

{{-- ===== STATUS TABS ===== --}}
<div class="row pt-0">
  <div class="col-xl-12 col-lg-12 col-md-12 d-flex align-items-center justify-content-end">
    <div class="text-sm-right status-filter">
      <div class="btn-group mb-3">
        <button type="button" class="btn btn-light text-white btn_tab bg-primary active" data-filter="All">{{ __('All') }}</button>
        <button type="button" class="btn btn-light bg-primary text-white btn_tab" data-filter="New">{{ __('New') }}</button>
        <button type="button" class="btn btn-light bg-primary text-white btn_tab" data-filter="In Process">{{ __('In Process') }}</button>
        <button type="button" class="btn btn-light bg-primary text-white btn_tab" data-filter="On Hold">{{ __('On Hold') }}</button>
        <button type="button" class="btn btn-light bg-primary text-white btn_tab" data-filter="Validation">{{ __('Validation') }}</button>
        <button type="button" class="btn btn-light bg-primary text-white btn_tab" data-filter="Closed">{{ __('Closed') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- ===== TABLA ===== --}}
<div class="row">
  <div class="col-lg-12 col-md-12">
    @if (session()->has('ticket_id') || session()->has('smtp_error'))
      <div class="alert alert-info bg-pr">
        @if (session()->has('ticket_id'))
          {!! Session::get('ticket_id') !!} {{ Session::forget('ticket_id') }}
        @endif
        @if (session()->has('smtp_error'))
          {!! Session::get('smtp_error') !!} {{ Session::forget('smtp_error') }}
        @endif
      </div>
    @endif
  </div>
  <div class="col-lg-12 col-md-12">
    <div class="card">
        <div class="card-header card-body table-border-style">
            <div class="table-responsive helpdesk-table-wrap">
                {{ $dataTable->table(['width' => '100%', 'class' => 'table w-100']) }}
            </div>
        </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  @include('layouts.includes.datatable-js')
  {{ $dataTable->scripts() }}

  {{-- Choices --}}
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

  <script>
    // Estados por defecto (como antes)
    window.__ticketStatuses = new Set(['new','in-progress','on-hold','validation']);
    const statusMap = {
      'All':'',
      'New':'new','In Process':'in-progress','On Hold':'on-hold',
      'Validation':'validation','Closed':'closed'
    };

    function activateDefaultStatusButtons() {
      $('.btn_tab').removeClass('active');
      $('.btn_tab').each(function(){
        const label = $(this).data('filter') || $(this).text().trim();
        const slug  = statusMap[label];
        if (slug && window.__ticketStatuses.has(slug)) $(this).addClass('active');
      });
    }

    function fitColumnsByMax(tableApi){
      tableApi.columns().every(function(){
        var header = $(this.header());
        if(!header.hasClass('fit-by-max')) return;

        // medir header y todas las celdas visibles
        var max = 0;

        // incluir header
        // Clon para medir sin padding ajeno
        var $hClone = header.clone().css({
          position:'absolute', left:'-9999px', top:'-9999px', width:'auto', display:'inline-block', visibility:'hidden'
        }).appendTo(document.body);
        max = Math.max(max, $hClone.outerWidth());
        $hClone.remove();

        // incluir celdas
        var cells = this.nodes();
        for (var i = 0; i < cells.length; i++) {
          var $c = $(cells[i]);
          // clonar contenido para medir sin límites de tabla
          var $clone = $c.clone().css({
            position:'absolute', left:'-9999px', top:'-9999px',
            width:'auto', display:'inline-block', visibility:'hidden', whiteSpace:'nowrap'
          }).appendTo(document.body);
          max = Math.max(max, $clone.outerWidth());
          $clone.remove();
        }

        // margen de seguridad
        max = Math.ceil(max) + 16;

        // fijar ancho para header + todas las celdas de la columna
        $(cells).css({ minWidth: max+'px', width: max+'px', maxWidth: max+'px' });
        header.css({ minWidth: max+'px', width: max+'px', maxWidth: max+'px' });
      });
    }
    // ==== INIT Choices (igual que Timesheet) ====
    let assignedChoices = null, categoryChoices = null;
    document.addEventListener('DOMContentLoaded', function(){
      assignedChoices = document.getElementById('filter-assigned-to')
        ? new Choices('#filter-assigned-to', {
            removeItemButton:true, shouldSort:false, placeholder:true,
            placeholderValue: document.getElementById('filter-assigned-to').dataset.placeholder
          })
        : null;

      categoryChoices = new Choices('#filter-category', {
        removeItemButton:true, shouldSort:false, placeholder:true,
        placeholderValue: document.getElementById('filter-category').dataset.placeholder
      });

      activateDefaultStatusButtons();
    });

    // Helpers para leer valores desde Choices
    function getMultiValues(id){
      const el = document.getElementById(id);
      if (!el) return [];
      return Array.from(el.selectedOptions || []).map(o => o.value).filter(Boolean);
    }

    // Adjunta filtros a DataTables (como en Timesheet)
    $(document).on('preXhr.dt', function(e, settings, data){
      if (settings.sTableId !== 'supports-table') return;
      data.statuses    = Array.from(window.__ticketStatuses || []);
      data.assigned_to = getMultiValues('filter-assigned-to');
      data.category_id = getMultiValues('filter-category');
      data.date_from   = $('#filter-date-from').val() || '';
      data.date_to     = $('#filter-date-to').val()   || '';
    });

    // Tabs de estado
    $(document).on('click', '.btn_tab', function(e){
      e.preventDefault();
      const label = $(this).data('filter') || $(this).text().trim();
      const slug  = statusMap[label] || '';

      if (label === 'All') {
        window.__ticketStatuses.clear();
        $('.btn_tab').removeClass('active'); $(this).addClass('active');
      } else if (slug === 'closed') {
        window.__ticketStatuses = new Set(['closed']);
        $('.btn_tab').removeClass('active'); $(this).addClass('active');
      } else {
        $('.btn_tab').filter((i,btn)=>($(btn).data('filter')||$(btn).text().trim())==='All').removeClass('active');
        window.__ticketStatuses.delete('closed');
        $('.btn_tab').filter((i,btn)=>statusMap[$(btn).data('filter')||$(btn).text().trim()]==='closed').removeClass('active');

        if (window.__ticketStatuses.has(slug)) { window.__ticketStatuses.delete(slug); $(this).removeClass('active'); }
        else { window.__ticketStatuses.add(slug); $(this).addClass('active'); }

        if (window.__ticketStatuses.size===0) {
          window.__ticketStatuses = new Set(['new','in-progress','on-hold','validation']);
          activateDefaultStatusButtons();
        }
      }
      $('#supports-table').DataTable().ajax.reload(null,false);
    });

    // Aplicar / Limpiar (idéntico patrón)
    $('#btn-apply-filters').on('click', function(e){
      e.preventDefault();
      $('#supports-table').DataTable().ajax.reload(null,false);
    });

    $('#btn-clear-filters').on('click', function(e){
      e.preventDefault();
      if (assignedChoices) assignedChoices.removeActiveItems();
      if (categoryChoices) categoryChoices.removeActiveItems();
      $('#filter-date-from').val(''); 
      $('#filter-date-to').val('');

      window.__ticketStatuses = new Set(['new','in-progress','on-hold','validation']);
      activateDefaultStatusButtons();

      $('#supports-table').DataTable().ajax.reload(null,false);
    });

    // Redibujar on-change
    $('#filter-assigned-to, #filter-category, #filter-date-from, #filter-date-to')
      .on('change', ()=> $('#supports-table').DataTable().ajax.reload(null,false));
  </script>
  
<script>
  document.addEventListener('DOMContentLoaded', function () {
    let ticketAutoRefreshTimer = null;
    const REFRESH_MS = 60000;
    let lastFilterInteractionAt = 0;

    function getTicketsTable() {
      if ($.fn.DataTable.isDataTable('#supports-table')) {
        return $('#supports-table').DataTable();
      }
      return null;
    }

    function markFilterInteraction() {
      lastFilterInteractionAt = Date.now();
    }

    function hasOpenModal() {
      return document.querySelector('.modal.show') !== null;
    }

    function isUserTypingOrSelecting() {
      const active = document.activeElement;
      if (!active) return false;

      const tag = (active.tagName || '').toUpperCase();

      // escribiendo en inputs, selects, textarea o editable
      if (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        active.isContentEditable
      ) {
        return true;
      }

      // foco dentro de filtros o widgets Choices
      if (
        active.closest('#filter-assigned-to') ||
        active.closest('#filter-category') ||
        active.closest('#filter-date-from') ||
        active.closest('#filter-date-to') ||
        active.closest('.choices') ||
        active.closest('.choices__inner') ||
        active.closest('.choices__list') ||
        active.closest('.choices__input')
      ) {
        return true;
      }

      return false;
    }

    function isUserInteractingWithFiltersRecently() {
      // espera 5 segundos desde la última interacción con filtros
      return (Date.now() - lastFilterInteractionAt) < 5000;
    }

    function shouldSkipRefresh() {
      return (
        hasOpenModal() ||
        isUserTypingOrSelecting() ||
        isUserInteractingWithFiltersRecently()
      );
    }

    function refreshTicketsTable() {
      const table = getTicketsTable();
      if (!table) return;

      if (shouldSkipRefresh()) return;

      table.ajax.reload(null, false); // conserva página actual
    }

    function startTicketAutoRefresh() {
      if (ticketAutoRefreshTimer) return;

      ticketAutoRefreshTimer = setInterval(function () {
        if (!document.hidden) {
          refreshTicketsTable();
        }
      }, REFRESH_MS);
    }

    function stopTicketAutoRefresh() {
      if (!ticketAutoRefreshTimer) return;
      clearInterval(ticketAutoRefreshTimer);
      ticketAutoRefreshTimer = null;
    }

    // Marca interacción en filtros
    $(document).on(
      'focus input change keydown click mousedown',
      '#filter-assigned-to, #filter-category, #filter-date-from, #filter-date-to, .choices, .choices *',
      function () {
        markFilterInteraction();
      }
    );

    // También cuando abren/click tabs
    $(document).on('click', '.btn_tab', function () {
      markFilterInteraction();
    });

    // Inicia
    startTicketAutoRefresh();

    // Pausa/reanuda según visibilidad
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stopTicketAutoRefresh();
      } else {
        refreshTicketsTable();
        startTicketAutoRefresh();
      }
    });

    window.startTicketAutoRefresh = startTicketAutoRefresh;
    window.stopTicketAutoRefresh = stopTicketAutoRefresh;
    window.refreshTicketsTable = refreshTicketsTable;
  });
</script>
@endpush
