@extends('layouts.main')
@section('page-title')
    {{ __('Manage Timesheet') }}
@endsection
@section('title')
    {{ __('Contact') }}
@endsection
@section('page-breadcrumb')
    {{ __('Timesheet') }}
@endsection
@push('css')
    @include('layouts.includes.datatable-css')
@endpush
@section('page-action')
@php
    $u = Auth::user();
    $type = strtolower(trim((string) ($u->type ?? '')));

    $isSuper = $u->hasRole('super admin');
    $isCompany = $u->hasRole('company') || $u->hasRole('Company');
    $isStaffRole = $u->hasRole('staff') || $u->hasRole('Staff');
    $isStaffType = $type === 'staff';

    $canValidate =
        $isSuper
        || $isCompany
        || $isStaffRole
        || $isStaffType;

    $canUnvalidate =
        $isSuper
        || Auth::user()->hasPermission('timesheet unvalidate');

    $canBill = $isSuper || Auth::user()->hasPermission('timesheet bill');
    $canInvoice = $isSuper || Auth::user()->hasPermission('timesheet invoice');
@endphp
    <div>
        @if($canBill)
          <button id="btn-bill"
                  class="btn btn-sm btn-success ms-2 d-none"
                  data-bs-toggle="tooltip"
                  data-bs-placement="bottom"
                  data-bs-trigger="hover"
                  title="{{ __('Billed selected (approved & not billed/paid)') }}">
            <i class="ti ti-receipt"></i>
          </button>
        @endif
        @if($canInvoice)
          <button id="btn-invoice"
                  class="btn btn-sm btn-success ms-2 d-none"
                  data-bs-toggle="tooltip"
                  data-bs-placement="bottom"
                  data-bs-trigger="hover"
                  title="{{ __('Invoiced selected (approved & not invoiced)') }}">
            <i class="ti ti-file-invoice"></i>
          </button>
        @endif
        @if($canValidate)
          <button id="btn-validate"
                  class="btn btn-sm btn-success ms-2 d-none"
                  data-bs-toggle="tooltip"
                  data-bs-placement="bottom"
                  data-bs-trigger="hover"
                  title="{{ __('Approved selected (not approved & not billed/invoiced/paid)') }}">
            <i class="ti ti-circle-check"></i>
          </button>
        @endif
        @if($canUnvalidate)
          <button id="btn-unvalidate"
                  class="btn btn-sm btn-warning ms-2 d-none"
                  data-bs-toggle="tooltip"
                  data-bs-placement="bottom"
                  data-bs-trigger="hover"
                  title="{{ __('Unapproved selected (approved & not billed/invoiced/paid)') }}">
            <i class="ti ti-circle-x"></i>
          </button>
        @endif
        @permission('timesheet create')
            <a data-url="{{ route('timesheet.create') }}" data-size="lg" data-ajax-popup="true"
                data-bs-toggle="tooltip" data-title="{{ __('Create New Timesheet') }}" title="{{ __('Create') }}"
                data-bs-trigger="hover"
                class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endpermission

    </div>
@endsection

@section('content')
        <div class="row mb-3">
            {{ Form::open(['route' => ['timesheet.index'], 'method' => 'GET', 'id' => 'project_submit']) }}
            <div class="row d-flex align-items-center justify-content-end">
            @if(\Laratrust::hasRole('super admin') || Auth::user()->hasPermission('timesheet validate') || \Laratrust::hasPermission('timesheet unvalidate'))
              <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12 mr-2">
                <div class="btn-box">
                  <label class="form-label">{{ __('Employee(s)') }}</label>
                  <select id="filter-consultants" class="form-control multi-select" multiple="multiple"
                          data-placeholder="{{ __('- Select employee(s) -') }}">
                    @foreach($consultants as $c)
                      <option value="{{ $c->id }}">{{ $c->name }}{{ $c->email ? ' - '.$c->email : '' }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            @endif
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 col-12 mr-2">
                  <div class="btn-box">
                    <label class="form-label">{{ __('Client(s)') }}</label>
                    <select id="filter-clients" class="form-control multi-select" multiple="multiple" data-placeholder="{{ __('- Select client(s) -') }}">
                      @foreach($clients as $cl)
                        {{-- value puede ser id de cliente/prospecto/organization --}}
                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>                        
                
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                    <div class="btn-box">
                        <label class="form-label">{{ __('Date From') }}</label>
                        <input type="date" id="filter-date-from" class="form-control" value="{{ $fromDefault }}">

                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                    <div class="btn-box">
                        <label class="form-label">{{ __('Date To') }}</label>
                        <input type="date" id="filter-date-to" class="form-control" value="{{ $toDefault }}">
                    </div>
                </div>
                <div class="col-auto mt-4 d-flex align-items-center gap-2">
                 <div class="dropdown">
                    <button id="btn-range"
                            type="button"
                            class="btn btn-outline-secondary btn-sm px-3 py-1 dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                      <span id="btn-range-label">{{ __('This Week') }}</span>
                    </button>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item range-item" href="#" data-preset="this-week">{{ __('This Week') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="last-week">{{ __('Last Week') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="this-month">{{ __('This Month') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="last-month">{{ __('Last Month') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="this-quarter">{{ __('This Quarter') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="last-quarter">{{ __('Last Quarter') }}</a></li>
                      <li><a class="dropdown-item range-item" href="#" data-preset="this-year">{{ __('This Year') }}</a></li>
                    </ul>
                  </div>
                  <a href="#"                                     
                     id="btn-apply-filters"
                     class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center"
                     style="width:34px;height:34px;padding:0;" data-bs-trigger="hover"
                     data-bs-toggle="tooltip" title="{{ __('Apply') }}">
                    <i class="ti ti-search"></i>
                  </a>
                  <a href="#"
                     id="btn-clear-filters"
                     class="btn btn-danger btn-sm d-inline-flex align-items-center justify-content-center"
                     style="width:34px;height:34px;padding:0;"  data-bs-trigger="hover"
                     data-bs-toggle="tooltip" title="{{ __('Clear') }}">
                    <i class="ti ti-trash-off text-white-off"></i>
                  </a>
                </div>

            </div>
            {{ Form::close() }}
        </div>
        <div class="row pt-0">
            <div class="col-xl-6 col-lg-6 col-md-6 d-flex align-items-left justify-content-start">
                <div id="ts-total-footer" class="mt-2 text-end fw-bold text-primary" style="display:none;">
                    {{ __('Selected total:') }} <span id="ts-total-hours">0.00</span> {{ __('hours') }}
                </div>                        
            </div>                                    
            <div class="col-xl-6 col-lg-6 col-md-6 d-flex align-items-center justify-content-end">
                <div class="text-sm-right status-filter">
                    <div class="btn-group mb-3">
                        <button type="button" class="btn btn-light  text-white btn_tab  bg-primary active"
                            data-filter="All" data-status="All">{{ __('All') }}</button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Unapproved">{{ __('Unapproved') }}</button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Approved">{{ __('Approved') }}</button>
                        @if(!\Laratrust::hasRole(['Customer','Client','Helpdesk']))
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Billed">{{ __('Billed') }}</button>
                        <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Paid">{{ __('Paid') }}</button>
                        @endif
                        @php
                            $canInvoice = \Laratrust::hasRole('super admin')
                            || \Laratrust::hasPermission([
                                'invoice manage',
                                'invoice view',
                                'invoice show',
                                'invoice create',
                                'invoice edit',
                                'invoice delete'
                            ]);
                            $isCustomerLike  = \Laratrust::hasRole(['Customer','Client']);
                        @endphp
                        @if($canInvoice && !$isCustomerLike)
                            <button type="button" class="btn btn-light bg-primary text-white btn_tab"
                            data-filter="Invoiced">{{ __('Invoiced') }}</button>
                        @endif
                    </div>
                </div>
            </div><!-- end col-->
        </div>      
    <div class="row">
        <div class="col-xl-12">
            <div class="card">



                <div class="card-body table-border-style">
                    <div class="table-responsive overflow_hidden">
                        {{ $dataTable->table(['id' => 'timesheet-table', 'width' => '100%']) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Password Confirm Modal -->
<div class="modal fade" id="tsPasswordModal" tabindex="-1" aria-labelledby="tsPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="tsPasswordForm" class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="tsPasswordModalLabel">{{ __('Confirm action') }}</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">
          <h4><strong>Enter your password to confirm the <span id="tsVerbStrong">approval</span>.</strong></h4><br>
            <span id="tsWarning">
            Make sure you are performing the correct action, as it is not reversible.
            </span>
        </p>
        <div class="mb-3">
          <label for="tsPasswordInput" class="form-label">{{ __('Password') }}</label>
          <input type="password" class="form-control" id="tsPasswordInput" name="password" required autocomplete="current-password">
          <div class="invalid-feedback">{{ __('Password is required') }}</div>
        </div>
        <input type="hidden" id="tsAction" value=""> <!-- validate | unvalidate -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary" id="tsConfirmBtn">
          <i class="ti ti-lock"></i> <span id="tsConfirmText">Confirm</span>
        </button>
      </div>
    </form>
  </div>
</div>

@endsection
@push('scripts')
    @include('layouts.includes.datatable-js')
    {{ $dataTable->scripts() }}
    
    <script>
    $(function(){
      const $ = window.jQuery;
      window.pendingIds = [];
      window.pendingAction = '';
      const modalEl = document.getElementById('tsPasswordModal');
      const modal = new bootstrap.Modal(modalEl);
      const $password = $('#tsPasswordInput');
    
      function getSelectedIdsFor(action){
        const $sel = $('.ts-bulk:checked');
        if (action === 'validate') {
          return $sel.filter(function(){
            const d = $(this).data();
            return +d.validated === 0 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
          }).map(function(){ return $(this).val(); }).get();
        } else {
          return $sel.filter(function(){
            const d = $(this).data();
            return +d.validated === 1 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
          }).map(function(){ return $(this).val(); }).get();
        }
      }
    
// ejemplo jQuery
$('#tsPasswordForm').on('submit', function(e){
  e.preventDefault();
  const pwd = $.trim($('#tsPasswordInput').val() || '');
  if (!pwd) { $('#tsPasswordInput').addClass('is-invalid'); return; }https://github.com/ajaxorg/ace/wiki/Default-Keyboard-Shortcuts
  $('#tsPasswordInput').removeClass('is-invalid');

  const verifyUrl = "{{ route('timesheet.verify-password') }}";
  const token = "{{ csrf_token() }}";
  // 1) Verificar contraseña
  $.post({
    url: verifyUrl,
    data: { password: pwd, _token: token },
  }).done(function(res){
    const route = window.pendingAction === 'validate'
      ? "{{ route('timesheet.bulk-validate') }}"
      : "{{ route('timesheet.bulk-unvalidate') }}";
console.log('res',res);
    $.post({
      url: route,
      data: { ids: window.pendingIds, password: pwd, _token: token },
      success: function(res){
        // cierra modal solo con éxito
        const modalEl = document.getElementById('tsPasswordModal');
        const modalIns = bootstrap.Modal.getInstance(modalEl);
        if(modalIns) modalIns.hide();
        toastrs('Success', res.message || 'Done', 'success');
        if (window.LaravelDataTables && window.LaravelDataTables['timesheet-table']) {
          window.LaravelDataTables['timesheet-table'].ajax.reload(null, false);
        } else {
          location.reload();
        }
      },
      error: function(xhr){
        const msg = xhr.responseJSON?.message || 'Error';
        toastrs('Error', msg, 'error');
      }
    });

  }).fail(function(xhr){
    // NO cerrar el modal; mostrar mensaje
    const msg = xhr.responseJSON?.message || 'Incorrect password';
    toastrs('Error', msg, 'error');
    $('#tsPasswordInput').val('').trigger('focus');
  });
});
    
      // limpiar al cerrar
      modalEl.addEventListener('hidden.bs.modal', () => {
      window.pendingIds = [];
      window.pendingAction = '';
      $password.val('').removeClass('is-invalid');
      });    
    });
    </script>    

    <script>
        document.addEventListener('DOMContentLoaded', function () {
          const fromEl = document.getElementById('filter-date-from');
          const toEl   = document.getElementById('filter-date-to');
          const label  = document.getElementById('btn-range-label');

          // ===== inicializa Selects múltiples (Select2 si está, fallback Choices) =====
        (function initMultiSelects () {
          const consEl = document.getElementById('filter-consultants'); // puede NO existir
          const cliEl  = document.getElementById('filter-clients');
        
          // Helpers
          const hasSelect2 = !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2);
          const hasChoices = !!window.Choices;
          const isValidEl  = (el) => !!el && (
            el.tagName === 'SELECT' || (el.tagName === 'INPUT' && el.type === 'text')
          );
          const placeholderOf = (el) =>
            (el && el.dataset && el.dataset.placeholder) || el?.getAttribute('data-placeholder') || '';
        
          // Exponer refs globales solo si usamos Choices
          window.consultantChoices = null;
          window.clientChoices = null;
        
          // 1) Si hay Select2, úsalo (con jQuery pero sin $ en el código)
          if (hasSelect2) {
            if (isValidEl(consEl)) window.jQuery(consEl).select2({ width:'100%', placeholder: placeholderOf(consEl), allowClear:true });
            if (isValidEl(cliEl))  window.jQuery(cliEl).select2({  width:'100%', placeholder: placeholderOf(cliEl),  allowClear:true });
            return;
          }
        
          // 2) Si no hay Select2 pero sí Choices, úsalo (evita doble init con data-choices="active")
          if (hasChoices) {
            if (isValidEl(consEl) && !consEl.dataset.choices) {
              window.consultantChoices = new Choices(consEl, {
                removeItemButton: true,
                shouldSort: false,
                placeholder: true,
                placeholderValue: placeholderOf(consEl)
              });
            }
            if (isValidEl(cliEl) && !cliEl.dataset.choices) {
              window.clientChoices = new Choices(cliEl, {
                removeItemButton: true,
                shouldSort: false,
                placeholder: true,
                placeholderValue: placeholderOf(cliEl)
              });
            }
          }
        })();
        
        // ===== utilidades de fecha =====
        const pad = n => String(n).padStart(2,'0');
        const ymd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
        const lastDayOfMonth = (y,m) => new Date(y, m+1, 0).getDate();
        const qOf = d => Math.floor(d.getMonth()/3)+1;
        const qStart = (d,q=null)=>{ const y=d.getFullYear(), q0=(q??qOf(d))-1; return new Date(y,q0*3,1); };
        const qEnd   = (d,q=null)=>{ const s=qStart(d,q); return new Date(s.getFullYear(), s.getMonth()+3, 0); };
        
        // NUEVO: inicio de semana (domingo)
        const startOfWeekSun = (d) => {
          const t = new Date(d.getFullYear(), d.getMonth(), d.getDate());
          const dow = t.getDay(); // 0=domingo
          return new Date(t.getFullYear(), t.getMonth(), t.getDate() - dow);
        };
        
        function setRange(preset){
          const now = new Date(); let s,e;
        
          if (preset === 'this-week') {
            s = startOfWeekSun(now);
            e = new Date(s.getFullYear(), s.getMonth(), s.getDate() + 6);
            label.textContent = "{{ __('This Week') }}";
          }
          else if (preset === 'last-week') {
            const thisStart = startOfWeekSun(now);
            s = new Date(thisStart.getFullYear(), thisStart.getMonth(), thisStart.getDate() - 7);
            e = new Date(thisStart.getFullYear(), thisStart.getMonth(), thisStart.getDate() - 1);
            label.textContent = "{{ __('Last Week') }}";
          }
          else if (preset === 'this-month') {
            s = new Date(now.getFullYear(), now.getMonth(), 1);
            e = new Date(now.getFullYear(), now.getMonth(), lastDayOfMonth(now.getFullYear(), now.getMonth()));
            label.textContent = "{{ __('This Month') }}";
          }
          else if (preset === 'last-month') {
            const y = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();
            const m = now.getMonth() === 0 ? 11 : now.getMonth() - 1;
            s = new Date(y, m, 1);
            e = new Date(y, m, lastDayOfMonth(y, m));
            label.textContent = "{{ __('Last Month') }}";
          }
          else if (preset === 'this-quarter') {
            s = qStart(now);
            e = qEnd(now);
            label.textContent = "{{ __('This Quarter') }}";
          }
          else if (preset === 'last-quarter') {
            // anterior trimestre (si es Q1 → Q4 del año anterior)
            const tq = qOf(now);
            const y  = now.getFullYear() - (tq === 1 ? 1 : 0);
            const q  = (tq === 1 ? 4 : tq - 1);
            const ref = new Date(y, 0, 1);
            s = qStart(ref, q);
            e = qEnd(ref, q);
            label.textContent = "{{ __('Last Quarter') }}";
          }
          else if (preset === 'this-year') {
            s = new Date(now.getFullYear(), 0, 1);
            e = new Date(now.getFullYear(), 11, 31);
            label.textContent = "{{ __('This Year') }}";
          }
          else {
            return;
          }
        
          fromEl.value = ymd(s);
          toEl.value   = ymd(e);
          drawWithFilters();
        }
        
          const $ = window.jQuery;
          let table = null;
        
          function bindPreXhr($table){
            $table.off('preXhr.dt.tsfilters').on('preXhr.dt.tsfilters', function(e, settings, data){
              // Fechas
              data.date_from = fromEl.value || '';
              data.date_to   = toEl.value   || '';
        
              // Status por pestañas (si lo usas)
              const $active = $('.btn_tab.active');
              if ($active.length) data.status = $active.data('filter') || 'All';
        
              // NUEVO: filtros multi-select
              // Si usas Select2:
              const consultants = $('#filter-consultants').val() || [];
              const clients     = $('#filter-clients').val() || [];
        
              // Enviar como arrays; Laravel/Yajra los recibe como data[consultant_ids][]=1...
              data.consultant_ids = consultants;
              data.client_ids     = clients;
            });
          }
        
          function ensureTable(){
            if (table) return table;
            if (window.LaravelDataTables && window.LaravelDataTables['timesheet-table']) {
              table = window.LaravelDataTables['timesheet-table'];
              bindPreXhr($(table.table().node()));
              return table;
            }
            const $t = $('#timesheet-table');
            if ($t.length && $.fn.dataTable.isDataTable($t)) {
              table = $t.DataTable();
              bindPreXhr($t);
              return table;
            }
            return null;
          }
        
          $(document).on('init.dt.tsinit', function(e, settings){
            const $n = $(settings.nTable);
            if (!table && $n.attr('id') === 'timesheet-table') {
              table = $n.DataTable();
              bindPreXhr($n);
            }
          });
        
          function drawWithFilters(){
            const api = ensureTable();
            if (api) api.draw(false);
          }
        
          // Eventos UI
          document.querySelectorAll('.range-item').forEach(a=>{
            a.addEventListener('click', e => { e.preventDefault(); setRange(a.dataset.preset); });
          });
          document.getElementById('btn-apply-filters')?.addEventListener('click', e => {
            e.preventDefault(); drawWithFilters();
          });
          
            document.getElementById('btn-clear-filters')?.addEventListener('click', e => {
              e.preventDefault();
            
              // 1) Fechas: volver a "This Week" (domingo–sábado)
              // (usa tu setRange existente, que ya pone from/to, el label y hace draw)
              setRange('this-week');
            
              // 2) (Opcional) limpiar selects, sin romper si no hay Choices/Select2
              const hasSelect2 = !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2);
            
              // Clientes
              if (window.clientChoices && typeof window.clientChoices.removeActiveItems === 'function') {
                window.clientChoices.removeActiveItems();
              } else if (hasSelect2 && window.jQuery('#filter-clients').length) {
                window.jQuery('#filter-clients').val(null).trigger('change');
              } else {
                const elCli = document.getElementById('filter-clients');
                if (elCli) { elCli.selectedIndex = -1; Array.from(elCli.options).forEach(o => o.selected = false); }
              }
            
              // Consultores (puede no existir por permisos)
              if (window.consultantChoices && typeof window.consultantChoices.removeActiveItems === 'function') {
                window.consultantChoices.removeActiveItems();
              } else if (hasSelect2 && window.jQuery('#filter-consultants').length) {
                window.jQuery('#filter-consultants').val(null).trigger('change');
              } else {
                const elCon = document.getElementById('filter-consultants');
                if (elCon) { elCon.selectedIndex = -1; Array.from(elCon.options).forEach(o => o.selected = false); }
              }
            
              // Nota: setRange('this-week') ya llamó drawWithFilters().
              // Si prefieres un solo draw, quita el draw dentro de setRange y llama aquí: drawWithFilters();
            });

          [fromEl, toEl].forEach(el=>{
            el.addEventListener('change', ()=>{ label.textContent="{{ __('Custom Date') }}"; });
            el.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); drawWithFilters(); } });
          });
        
          // Redibuja al cambiar selects
          $('#filter-consultants, #filter-clients').on('change', drawWithFilters);
        
          // Opcional: cambiar status al hacer click en pestañas y redibujar
          $('.btn_tab').on('click', function(e){
            e.preventDefault();
            $('.btn_tab').removeClass('active');
            $(this).addClass('active');
            drawWithFilters();
          });
        });
    </script>

    <script>
    $(function () {
    function hideAllTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
          const tip = bootstrap.Tooltip.getInstance(el);
          if (tip) tip.hide();
        });
        // elimina posibles restos en el DOM
        document.querySelectorAll('.tooltip.show').forEach(t => t.remove());
      }
    
      // Si quieres ocultar *solo* de un botón concreto:
      function hideTooltipOf(el) {
        if (!el) return;
        const tip = bootstrap.Tooltip.getInstance(el);
        if (tip) tip.hide();
      }
  
      $(document).on('preXhr.dt.tstips draw.dt.tstips', function () {
        hideAllTooltips();
      });
    
      // Cierra al salir del botón por si acaso
      $('#btn-validate, #btn-unvalidate').on('mouseleave blur', function () {
        hideTooltipOf(this);
      });
  
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body' });
      });
        
      // seleccionar/deseleccionar todos
      $(document).on('change', '#ts-bulk-all', function(){
        $('.ts-bulk').prop('checked', this.checked);
        refreshBulkButtons();
        updateSelectedTotal();
      });
    

      // cuando cambian checks individuales o se redibuja la tabla
      $(document).on('change', '.ts-bulk', function(){
          refreshBulkButtons();
          updateSelectedTotal();
      });
      
      $('#timesheet-table').on('draw.dt', refreshBulkButtons);
    
      function $selected() { return $('.ts-bulk:checked'); }
    
      function refreshBulkButtons(){
        const $sel = $('.ts-bulk:checked');
        const any  = $sel.length > 0;
    
        const canValidateSome = any && $sel.filter(function(){
          const d = $(this).data();
          return +d.validated === 0 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
        }).length > 0;

        const canUnvalidateSome = any && $sel.filter(function(){
          const d = $(this).data();
          return +d.validated === 1 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
        }).length > 0;
    
        const canBillSome = any && $sel.filter(function(){
          const d = $(this).data();
          return +d.validated === 1 && +d.billed === 0 && +d.paid === 0;
        }).length > 0;

        const canInvoiceSome = any && $sel.filter(function(){
          const d = $(this).data();
          return +d.validated === 1 && +d.invoiced === 0;
        }).length > 0;


        const btnV = document.getElementById('btn-validate');
        const btnU = document.getElementById('btn-unvalidate');
        const btnB = document.getElementById('btn-bill');
        const btnI = document.getElementById('btn-invoice');
    
        // Antes de ocultar/desactivar, esconde sus tooltips
        if (!canValidateSome) hideTooltipOf(btnV);
        if (!canUnvalidateSome) hideTooltipOf(btnU);
        if (!canBillSome) hideTooltipOf(btnB);
        if (!canInvoiceSome) hideTooltipOf(btnI);
    
        $('#btn-validate').toggleClass('d-none', !canValidateSome).prop('disabled', !canValidateSome);
        $('#btn-unvalidate').toggleClass('d-none', !canUnvalidateSome).prop('disabled', !canUnvalidateSome);
        $('#btn-bill').toggleClass('d-none', !canBillSome).prop('disabled', !canBillSome);
        $('#btn-invoice').toggleClass('d-none', !canInvoiceSome).prop('disabled', !canInvoiceSome);
      }
    

      // VALIDAR: solo envía los que cumplan la regla de validar
      $('#btn-validate').on('click', function(e){
        e.preventDefault();
        hideAllTooltips();
        const ids = $selected().filter(function(){
          const d = $(this).data();
          return +d.validated === 0 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
        }).map(function(){ return $(this).val(); }).get();
    
        if(!ids.length){ toastrs('Error', "{{ __('Select at least one valid row to validate.') }}", 'error'); return; }
        window.pendingAction = 'validate';
        window.pendingIds = ids;
        updateTsModalUI('validate');
        new bootstrap.Modal(document.getElementById('tsPasswordModal')).show();
        return; // <- evita que se ejecute el $.post directo
    
        $.ajax({
          url: route,
          method: 'POST',
          dataType: 'json',
          data: { ids: pendingIds, password: pwd, _token: "{{ csrf_token() }}" },
          success: (r) => {
            modal.hide();
            toastrs('Success', r.message || "{{ __('Done.') }}", 'success');
            window.LaravelDataTables['timesheet-table'].ajax.reload(null, false);
          },
          error: (xhr) => {
            const msg = xhr.responseJSON?.message || "{{ __('Incorrect password or error occurred.') }}";
            toastrs('Error', msg, 'error');
            $password.addClass('is-invalid');
            $password.val('').trigger('focus');
          }
        });

      });
    
      // INVALIDAR: solo envía los válidos y no facturados/facturables/pagados
      $('#btn-unvalidate').on('click', function(e){
        e.preventDefault();
        hideAllTooltips();
        const ids = $selected().filter(function(){
          const d = $(this).data();
          return +d.validated === 1 && +d.billed === 0 && +d.invoiced === 0 && +d.paid === 0;
        }).map(function(){ return $(this).val(); }).get();
    
        if(!ids.length){ toastrs('Error', "{{ __('Select at least one valid row to unvalidate (not billed/invoiced/paid).') }}", 'error'); return; }
        window.pendingAction = 'unvalidate';
        window.pendingIds = ids;
        updateTsModalUI('unvalidate');
        new bootstrap.Modal(document.getElementById('tsPasswordModal')).show();
        return; // <- corta el POST directo
    
        $.ajax({
          url: route,
          method: 'POST',
          dataType: 'json',
          data: { ids: pendingIds, password: pwd, _token: "{{ csrf_token() }}" },
          success: (r) => {
            modal.hide();
            toastrs('Success', r.message || "{{ __('Done.') }}", 'success');
            window.LaravelDataTables['timesheet-table'].ajax.reload(null, false);
          },
          error: (xhr) => {
            const msg = xhr.responseJSON?.message || "{{ __('Incorrect password or error occurred.') }}";
            toastrs('Error', msg, 'error');
            $password.addClass('is-invalid');
            $password.val('').trigger('focus');
          }
        });
      });
      
      
      // estado inicial
      refreshBulkButtons();
    });
    
    $(document).on('click keydown change', '.ts-readonly', function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();
      // revertir cualquier cambio si el navegador lo aplicó
      this.checked = this.defaultChecked;
      return false;
    });    
    
    // ==== SUMA DE HORAS SELECCIONADAS ====
function updateSelectedTotal() {
  let totalMinutes = 0;

  $('.ts-bulk:checked').each(function(){
    // Recupera las horas y minutos desde las celdas de la fila
    const row = $(this).closest('tr');
    const totalText = row.find('td').filter(function(){
      return $(this).text().trim().match(/^\d{2}:\d{2}$/);
    }).text().trim();

    if(totalText){
      const [h,m] = totalText.split(':').map(Number);
      totalMinutes += h*60 + m;
    }
  });

  const totalDecimal = (totalMinutes / 60).toFixed(2);
  $('#ts-total-hours').text(totalDecimal);

  // Mostrar u ocultar el footer según haya selección
  if($('.ts-bulk:checked').length > 0){
    $('#ts-total-footer').slideDown(150);
  } else {
    $('#ts-total-footer').slideUp(150);
  }
}

// Vincula el evento a los cambios de selección y a la recarga de tabla
$('#timesheet-table').on('draw.dt', updateSelectedTotal);

function updateTsModalUI(action){
  const isApprove = action === 'validate';
  const title     = isApprove ? 'Confirm approval' : 'Confirm unapproval';
  const verb      = isApprove ? 'approval'         : 'unapproval';
  const confirm   = isApprove ? 'Approve'          : 'Unapprove';

  // título
  document.getElementById('tsPasswordModalLabel').textContent = title;

  // verbo dentro del <strong>
  document.getElementById('tsVerbStrong').textContent = verb;

  // botón (texto y estilo opcional)
  const btn = document.getElementById('tsConfirmBtn');
  document.getElementById('tsConfirmText').textContent = confirm;

  // (opcional) cambia color del botón
  btn.classList.remove('btn-primary','btn-success','btn-warning');
  btn.classList.add(isApprove ? 'btn-success' : 'btn-warning');
}

    </script>
@endpush

