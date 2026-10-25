<input type="hidden" name="submit_mode" id="submit_mode" value="save_and_new">


{{ Form::open(['route' => 'timesheet.store','id' => 'timesheet-form','class' => 'needs-validation '.($errors->any()?'was-validated':''), 'novalidate']) }}
@php($date = $date ?? \Carbon\Carbon::now()->toDateString())
<div class="modal-body">
  <div class="row">
    <div class="col-md-12">
      <div class="d-flex radio-check">
        @if (module_is_active('Hrm'))
        <div class="form-check form-check-inline form-group col-md-4">
            <input type="radio" id="clockin"  value="Clock in/Clock out" name="type"
              class="form-check-input code"
              {{ old('type', $prefill['type'] ?? '') === 'Clock in/Clock out' ? 'checked' : '' }}>          
          <label class="custom-control-label" for="clockin">{{ __('Clock In/Clock Out Wise') }}</label>
        </div>
        @endif
        @if (module_is_active('Taskly'))
        <div class="form-check form-check-inline form-group col-lg-3">
            <input type="radio" id="project" value="Project" name="type"
              class="form-check-input code"
              {{ old('type', $prefill['type'] ?? '') === 'Project' ? 'checked' : '' }}>          
          <label class="custom-control-label" for="project">{{ __('Project Wise') }}</label>
        </div>
        @endif
        <div class="form-check form-check-inline form-group col-lg-3">
            <input type="radio" id="manually" value="Manually" name="type"
              class="form-check-input code"
              {{ old('type', $prefill['type'] ?? '') === 'Service Wise' ? 'checked' : '' }}>          
          <label class="custom-control-label" for="manually">{{ __('Service Wise') }}</label>
        </div>
      </div>
    </div>

    {{-- User oculto = autenticado --}}
    <input type="hidden" name="user_id" id="user_id" value="{{ auth()->id() }}">

    <div class="col-md-3">
      <div class="form-group">
        {{ Form::label('date', __('Date'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::date(
          'date',
          old('date', $date ?? \Carbon\Carbon::now()->toDateString()),
          ['class'=>'form-control date','required'=>'required','max'=>\Carbon\Carbon::now()->toDateString(),'id'=>'date-input']
        ) }}
      </div>
    </div>

    {{-- Tiempos desde/hasta --}}
    <div class="col-md-3">
      <div class="form-group">
        <label class="col-form-label">{{ __('From time') }}</label>
        <input type="time" name="from_time" id="from_time" class="form-control @error('duration') is-invalid @enderror" step="60">
        {!! $errors->first('from_time', '<div class="invalid-feedback d-block">:message</div>') !!}
        </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="col-form-label">{{ __('To time') }}</label>
        <input type="time" name="to_time" id="to_time" class="form-control @error('duration') is-invalid @enderror" step="60">
        {!! $errors->first('to_time', '<div class="invalid-feedback d-block">:message</div>') !!}
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        {{ Form::label('duration', __('Total (hh:mm)'), ['class' => 'col-form-label']) }}<x-required></x-required>
        <input
          type="text"
          name="duration"
          id="duration"
          class="form-control @error('duration') is-invalid @enderror"
          placeholder="HH:MM"
          inputmode="numeric"
          maxlength="5"
          pattern="^(?:[01]\d|2[0-3]):[0-5]\d$"
          value="{{ old('duration', isset($timesheet) ? sprintf('%02d:%02d', $timesheet->hours, $timesheet->minutes) : '') }}"
        >
        {!! $errors->first('duration', '<div class="invalid-feedback d-block">:message</div>') !!}
      </div>
    </div>


    @if (module_is_active('Taskly'))
      <div class="col-md-6 project_div d-none">
        <div class="form-group">
          {{ Form::label('project_id', __('Project'), ['class' => 'col-form-label']) }}<x-required></x-required>
          {{ Form::select('project_id', $project, null, ['class' => 'form-control get_tax multi-select', 'id' => 'project_id', 'placeholder' => __('- Select project -')]) }}
        </div>
      </div>
        {{-- Project Wise: Customer Client derivado del Project --}}
        <div class="col-md-6 project_client_div d-none">
          <div class="form-group">
            {{ Form::label('customer_client_id', __('Client'), ['class' => 'col-form-label']) }}<x-required></x-required>
            {{ Form::select('customer_client_id', [], null, ['class' => 'form-control', 'id' => 'project_customer_client_id','placeholder' => __('- Select Client -')]) }}
          </div>
        </div>
      <div class="col-md-6 project_div d-none">
        <div class="form-group">
          {{ Form::label('task_id', __('Task'), ['class' => 'col-form-label']) }}<x-required></x-required>
          {{ Form::select('task_id', [], null, ['class' => 'form-control', 'id'=>'task_id', 'placeholder' => __('- Select Task -')]) }}
        </div>
      </div>
    @endif

    {{-- Manually: Customer + Customer Client --}}
    <div class="col-md-6 manually_div d-none">
      <div class="form-group">
        {{ Form::label('customer_id', __('Customer'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::select('customer_id', $customers ?? [], null, ['class' => 'form-control', 'id' => 'customer_id','placeholder' => __('- Select Customer -')]) }}
      </div>
    </div>
    <div class="col-md-6 manually_div d-none">
      <div class="form-group">
        {{ Form::label('customer_client_id', __('Client'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::select('customer_client_id', [], null, ['class' => 'form-control', 'id' => 'customer_client_id','placeholder' => __('- Select Client -')]) }}
      </div>
    </div>
    <div class="col-md-12">
      <div class="form-group">
        <label class="col-form-label">{{ __('Ticket (optional)') }}</label>
        <select name="ticket_id" id="ticket_id" class="form-control select2" data-minimum-results-for-search="0">
          <option value="">{{ __('(None)') }}</option>
            @if(!empty($prefilledTicket))
              <option value="{{ $prefilledTicket->id }}" selected>
                {{ $ticketLabel }}
              </option>
            @endif          
        </select>
      </div>
    </div>
    {{-- Description (obligatorio) --}}
    <div class="col-md-12">
      <div class="form-group">
        {{ Form::label('description', __('Description'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::textarea('description', old('description', $timesheet->description ?? ''), [
          'class' => 'form-control notes',
          'required' => 'required',
          'rows' => '3',
          'placeholder' => __('Enter Description')
        ]) }}
        <div class="invalid-feedback d-block d-none" id="desc_err">{{ __('Description is required.') }}</div>      
      </div>
    </div>

    {{-- Notes (ya no obligatorio) --}}
    <div class="col-md-12">
      <div class="form-group">
        {{ Form::label('notes', __('Notes'), ['class' => 'col-form-label']) }}
        {{ Form::textarea('notes', '', ['class' => 'form-control notes','rows'=>'3','placeholder'=>__('Enter Notes')]) }}
      </div>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">
    {{ __('Cancel') }}
  </button>

  {{-- Guarda y cierra (envío normal) --}}
  <button type="button" id="btn-save-close" class="btn btn-outline-primary"
          data-bs-toggle="tooltip" title="{{ __('Save and close') }}">
    {{ __('Create & Close') }}
  </button>

  {{-- Guarda y nuevo (default / Enter) --}}
  <button type="submit" id="btn-save-new" class="btn btn-primary"
          data-bs-toggle="tooltip" title="{{ __('Save and add another') }}">
    {{ __('Create & New') }}
  </button>
</div>
{{ Form::close() }}

{{-- === Scripts === --}}
<script>
$(function(){
    
  const $dur = $('#duration');
  const STORE_URL = @json(route('timesheet.store'));
  const $form = $('#timesheet-form');
  
  function updateRequirements(){
      const f = ($('#from_time').val()||'').trim();
      const t = ($('#to_time').val()||'').trim();
      $('#duration').prop('required', !(f && t)); // requerido si no hay rango
    }

    function markInvalid($el, msg){
      //$el[0].setCustomValidity(msg || ' ');     // <- fuerza :invalid
      $el.addClass('is-invalid').removeClass('is-valid');
      $el.nextAll('.invalid-feedback').first().remove();
      if (msg) $el.after('<div class="invalid-feedback d-block">'+msg+'</div>');
      $form.addClass('was-validated');
    }
    
    function clearInvalid($el){
      $el[0].setCustomValidity('');             // <- limpia :invalid
      $el.removeClass('is-invalid');
      $el.nextAll('.invalid-feedback').first().remove();
    }  
    
  function pad2(n){ return String(n).padStart(2,'0'); }

  // Redondea hacia el próximo cuarto y CAPEA en 23:59
  function roundUpQuarterCap(hhmm){
    let [h,m] = hhmm.split(':').map(Number);
    let mm = Math.ceil(m/15)*15;
    h += Math.floor(mm/60);
    mm = mm % 60;
    if(h >= 24){ h = 23; mm = 59; }
    return `${pad2(h)}:${pad2(mm)}`;
  }
  
  // Valida "HH:MM" 24h
  function isHHMM(v){
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(v);
  }

  // Limpieza al salir del campo: acepta "7:5", "07:5", "7:05" y lo normaliza
  $dur.on('blur', function(){
    let v = ($dur.val() || '').trim();
    if(!v) return;

    // admite 1-2 dígitos:1-2 dígitos
    const m = v.match(/^(\d{1,2}):(\d{1,2})$/);
    if(!m){ $dur.addClass('is-invalid'); return; }
    let h = Math.min(23, Math.max(0, parseInt(m[1],10)));
    let min = Math.min(59, Math.max(0, parseInt(m[2],10)));
    $dur.removeClass('is-invalid').val(`${pad2(h)}:${pad2(min)}`);
  });
  
  // Pre-selecciona el primer tipo una sola vez
  $(".code").first().trigger('click');

  // === Utilidades de tiempo ===
    function roundUpQuarter(hhmm){
      if(!hhmm) return '';
      const [h,m] = hhmm.split(':').map(Number);
      let mm = Math.ceil(m/15)*15, hh=h;
      if(mm===60){ mm=0; hh++; }
      if(hh>=24){ hh=23; mm=59; }
      return String(hh).padStart(2,'0')+':'+String(mm).padStart(2,'0');
    }
    function diffHM(from,to){
      const [fh,fm] = from.split(':').map(Number);
      const [th,tm] = to.split(':').map(Number);
      const a=fh*60+fm, b=th*60+tm;
      if(b<a) return null;
      const d=b-a;
      return String(Math.floor(d/60)).padStart(2,'0')+':'+String(d%60).padStart(2,'0');
    }
    function clampHours(v){
    v=parseInt(v||'0',10); if(isNaN(v)) v=0;
    return Math.min(23, Math.max(0, v));
  }
  function snapMinutes(v){
    v=parseInt(v||'0',10); if(isNaN(v)) v=0;
    const allowed=[0,15,30,45];
    if(allowed.includes(v)) return v;
    // acercar al permitido más cercano
    return allowed.reduce((a,b)=> Math.abs(b-v)<Math.abs(a-v)?b:a,0);
  }
  $('#hours').on('input blur', function(){ this.value = clampHours(this.value); });
  $('#minutes').on('input blur', function(){ this.value = snapMinutes(this.value); });
  
function validateDescription() {
  const $desc = $('[name="description"]:visible').first();
  const v = ($desc.val() || '').trim();
  if (!v) {
    toastrs('Error', '{{ __("Description is required.") }}', 'error');
    $desc.focus();
    return false;
  }
  return true;
}
  
    function roundUpQuarterWrap(hhmm){
      if(!hhmm) return '';
      let [h,m] = hhmm.split(':').map(Number);
      let mm = Math.ceil(m/15)*15;
      h += Math.floor(mm/60);
      mm = mm % 60;
      h  = h % 24;                      // 24:00 => 00:00 (día sig.)
      return String(h).padStart(2,'0')+':'+String(mm).padStart(2,'0');
    }
      function toMin(hhmm){ const [h,m]=hhmm.split(':').map(Number); return h*60+m; }
      function diffHMOvernight(from,to){
      const a=toMin(from), b=toMin(to);
      let d=b-a;
      if(d<0) d += 1440;               // cruza medianoche
      if(d===0) return null;           // no permitimos 24:00 ni 00:00
      const H=Math.floor(d/60), M=d%60;
      return String(H).padStart(2,'0')+':'+String(M).padStart(2,'0');
    }
    
      function diffSameDay(from, to){
        const a = toMin(from), b = toMin(to);
        if (b <= a) return null;              // <-- aquí se prohíbe overnight y “igual”
        const d = b - a;
        return `${pad2(Math.floor(d/60))}:${pad2(d%60)}`;
      }
  
    function computeFromTimes(){
        const $from = $('#from_time');
        const $to   = $('#to_time');
        const $dur  = $('#duration');
    
        const f = $from.val(), t = $to.val();
        if (f && t){
          const fr = roundUpQuarterCap(f);
          const tr = roundUpQuarterCap(t);
          $from.val(fr); $to.val(tr);
    
          const dur = diffSameDay(fr, tr);
          if (!dur){
            // Error: From >= To
            toastrs('Error', "{{ __('The From time must be earlier than the To time (same day).') }}", 'error');
            $dur.val('').prop('disabled', false);
            return;
          }
          // Redondea el total (por si trae minutos sueltos)
          $dur.val(roundUpQuarterCap(dur)).prop('disabled', true);
        } else {
          $dur.prop('disabled', false);
        }
      }
    
      $('#from_time,#to_time').on('change blur', computeFromTimes);

  // === Helpers de UI ===
  const CSRF  = @json(csrf_token());
  const URL_BY_PROJECT  = @json(route('timesheet.clients.by-project'));
  const URL_BY_CUSTOMER = @json(route('timesheet.clients.by-customer'));
  const URL_GET_TASKS   = @json(route('gettask'));
  
  const $ccProject = $('#project_customer_client_id');
  const $ccManual  = $('#customer_client_id');

  const req = (sel, on) => $(sel).prop('required', !!on);
  const ena = (sel, on) => $(sel).prop('disabled', !on);
  // Importante: NO VACIAR selects al cambiar tipo, solo mostrar/ocultar
  function toggleType(type){
    if(type==='Project'){
      $(".project_div, .project_client_div").removeClass('d-none');
      $(".manually_div").addClass('d-none');

      req('#project_id', true); req('#task_id', true); req('#project_customer_client_id', true);
      req('#customer_id', false); req('#customer_client_id', false);

      ena('#project_id', true); ena('#task_id', true); ena('#project_customer_client_id', true);
      ena('#customer_id', false); ena('#customer_client_id', false);
    }
    else if(type==='Manually'){
      $(".project_div, .project_client_div").addClass('d-none');
      $(".manually_div").removeClass('d-none');

      req('#customer_id', true); req('#customer_client_id', true);
      req('#project_id', false); req('#task_id', false); req('#project_customer_client_id', false);

      ena('#customer_id', true); ena('#customer_client_id', true);
      ena('#project_id', false); ena('#task_id', false); ena('#project_customer_client_id', false);
    }
    else{
      $(".project_div, .project_client_div, .manually_div").addClass('d-none');
      req('#project_id', false); req('#task_id', false); req('#project_customer_client_id', false);
      req('#customer_id', false); req('#customer_client_id', false);
      ena('#project_id', false); ena('#task_id', false); ena('#project_customer_client_id', false);
      ena('#customer_id', false); ena('#customer_client_id', false);
    }
  }

  function fillSelect($el, data, placeholder) {
    $el.prop('disabled', true)
       .empty()
       .append(`<option value="">${placeholder}</option>`);
    if (data) {
      Object.entries(data).forEach(([id, name]) => {
        $el.append(`<option value="${id}">${name}</option>`);
      });
    }
    $el.prop('disabled', false);
  }
  
    // MANUALLY: cuando cambia el Customer => cargar sus Customer Clients
  $(document).off('change', '#customer_id').on('change', '#customer_id', function () {
    const id = this.value;
    fillSelect($ccManual, null, `@lang('- Select Client -')`);
    if (!id) return;

    $.get(URL_BY_CUSTOMER, { customer_id: id })
      .done(data => fillSelect($ccManual, data, `@lang('- Select Client- ')`))
      .fail(() => toastrs('Error', `@lang('Could not load Clients')`, 'error'));
  });

  // PROJECT WISE: cuando cambia el Project => cargar Tasks y Customer Clients del customer de ese proyecto
  $(document).off('change', '#project_id').on('change', '#project_id', function () {
    const id = this.value;
    const $task = $('#task_id');

    // limpiar selects dependientes
    fillSelect($ccProject, null, `@lang('Select Client')`);
    $task.prop('disabled', true).find('option:not(:first)').remove();

    if (!id) return;

    // 1) Tasks del proyecto (POST según tu endpoint)
    $.post(URL_GET_TASKS, { project_id: id, _token: CSRF })
      .done(data => {
        $task.prop('disabled', false);
        Object.entries(data || {}).forEach(([tid, title]) => {
          $task.append(`<option value="${tid}">${title}</option>`);
        });
      })
      .fail(() => {
        $task.prop('disabled', false);
        toastrs('Error', `@lang('Could not load Tasks')`, 'error');
      });

    // 2) Customer Clients del customer asociado al proyecto (GET)
    $.get(URL_BY_PROJECT, { project_id: id })
      .done(data => fillSelect($ccProject, data, `@lang('- Select Client -')`))
      .fail(() => toastrs('Error', `@lang('Could not load Clients')`, 'error'));
  });
  
  $(document).on('input change blur', '[name="description"]', validateDescription);
  
  // Cambiar tipo (un solo handler)
  $(document).on('change',"[name='type']",function(){
    const type = $(this).val();
    toggleType(type);

    // Si ya hay selección previa, re-dispara las cargas (NO limpiamos)
    if(type==='Project' && $('#project_id').val()){
      $('#project_id').trigger('change');
    }
    if(type==='Manually' && $('#customer_id').val()){
      $('#customer_id').trigger('change');
    }

  });


  $('#duration,#from_time,#to_time').on('input change', function(){
    clearInvalid($(this));
  });

  $('#from_time,#to_time').on('input change', updateRequirements);
  updateRequirements();

  // No dejar que AJAX pise valores cuando hay rango
  $('form').on('submit', computeFromTimes);


    $('form').on('submit', function(e){
          const $from = $('#from_time');
          const $to   = $('#to_time');
          const $dur  = $('#duration');
        
          clearInvalid($from); clearInvalid($to); clearInvalid($dur);
        
          const f = ($from.val() || '').trim();
          const t = ($to.val()   || '').trim();
          const d = ($dur.val()  || '').trim();
        
          // Caso A: ambos tiempos → validar que From < To (mismo día)
          if (f && t) {
            const fr = roundUpQuarterCap(f);
            const tr = roundUpQuarterCap(t);
            const dur = diffSameDay(fr, tr);      // null si From >= To
            if (!dur) {
              e.preventDefault(); e.stopImmediatePropagation();
              toastrs('Error', "{{ __('The From time must be earlier than the To time (same day).') }}", 'error');
              markInvalid($from, "{{ __('The From time must be earlier than the To time (same day).') }}");
              markInvalid($to,   "");            
              return false;
            }
            // opcional: fija el total redondeado
            $dur.val(roundUpQuarterCap(dur));
            return true; // OK, dejamos enviar
          }
        
          // Caso B: uno solo de los tiempos → error
          if ((f && !t) || (!f && t)) {
            e.preventDefault(); e.stopImmediatePropagation();
            toastrs('Error', "{{ __('Provide both From and To times, or leave both empty and fill Total (hh:mm).') }}", 'error');
            markInvalid($from, "{{ __('Provide both From and To times, or leave both empty and fill Total (hh:mm).') }}");
            markInvalid($to,   "");            
            return false;
          }
        
          // Caso C: sin tiempos → validar duration
          if (!/^\d{1,2}:\d{2}$/.test(d)) {
            e.preventDefault(); e.stopImmediatePropagation();
            toastrs('Error', "{{ __('Please enter Total (hh:mm).') }}", 'error');
            markInvalid($dur, "{{ __('Please enter Total (hh:mm).') }}");
            return false;
          }
          let [h, m] = d.split(':').map(v => parseInt(v,10));
          if (h === 0 && m === 0 || h > 23 || m > 59) {
            e.preventDefault(); e.stopImmediatePropagation();
            toastrs('Error', "{{ __('Time must be between 00:01 and 23:59.') }}", 'error');
            markInvalid($dur, "{{ __('Time must be between 00:01 and 23:59.') }}");
            return false;
          }
          // normaliza y redondea al próximo cuarto, capeando en 23:59
          $dur.val(roundUpQuarterCap(`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`));
          return true;
      });
      
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        if (!bootstrap.Tooltip.getInstance(el)) new bootstrap.Tooltip(el);
      });
    
      // Click en "Save & Close" => modo cerrar y submit normal
      $('#btn-save-close').on('click', function(){
        $('#submit_mode').val('save_and_close');
        $form.trigger('submit');
      });
    
      // (Opcional) “Save & New” explícito por click; por Enter ya vale 'save_and_new'
      $('#btn-save-new').on('click', function(){
        $('#submit_mode').val('save_and_new');
      });    
      
    function resetFormForNew(){
        // tiempos
        $('#from_time, #to_time').val('');
        $('#duration').val('').prop('disabled', false);
        $('#btn-save-new').val('').prop('disabled', false);
    
        // selects (no tocar los radios de "type")
        //$('#project_id, #task_id, #project_customer_client_id, #customer_id, #customer_client_id')
        //  .val('').trigger('change');
    
        // textos
        $('[name="description"]').val('');
        $('[name="notes"]').val('');
    
        // clases/feedback de validación
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        $form.find('.invalid-feedback').remove();
    
        // requeridos dinámicos
        updateRequirements();
    
        // foco
        $('[name="from_time"]').trigger('focus');
    }
      
    function renderErrors(errors){
        for (const [field, msgs] of Object.entries(errors || {})) {
          const $el = $('[name="'+field+'"]');
          if ($el.length) markInvalid($el, (msgs && msgs[0]) || 'Invalid');
        }
    }  
    
  $form.on('submit', function(e){
    // Tu validación existente corre aquí (NO la quites).
    // Asegúrate de que tu handler de validación termine con `return true;` (como ya estaba).
    // Aquí enganchamos al final: si era "save_and_new" => AJAX.
    if (!validateDescription()){
      e.preventDefault(); e.stopImmediatePropagation();
      toastrs('Error', "{{ __('Description is required.') }}", 'error');
      return false;
    }
    const mode = $('#submit_mode').val() || 'save_and_new';
    if (mode !== 'save_and_new') {
      // Save & Close => envío normal
      return true;
    }

    // Save & New => AJAX + limpiar y mantener abierto
    e.preventDefault(); e.stopImmediatePropagation();

    // limpia errores viejos
    $form.find('.invalid-feedback').remove();
    $form.find('.is-invalid').removeClass('is-invalid');

    $.ajax({
      url: STORE_URL,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': @json(csrf_token()),
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: $form.serialize(),
      success: function(resp){
        toastrs('Success', (resp && resp.message) || "{{ __('Timesheet successfully created.') }}", 'success');
        resetFormForNew();
      },
      error: function(xhr){
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
          renderErrors(xhr.responseJSON.errors);
          toastrs('Error', "{{ __('Please correct the highlighted fields.') }}", 'error');
        } else {
          toastrs('Error', "{{ __('Something went wrong.') }}", 'error');
        }
      }
    });

    return false;
  });
  
});  

$(function(){
  const $type = $('input[name="type"]');
  const $project = $('#project_id');
  const $task = $('#task_id');
  const $client = $('#client_id');
  const $customer = $('#customer_id'); // si lo tienes
  const $ticket = $('#ticket_id');

  function reloadTickets(){
    const type = $('input[name="type"]:checked').val();



    if(!type) return;
    $.ajax({
      url: "{{ route('timesheet.tickets.for') }}",
      data: {
        type,
        project_id: $project.val(),
        task_id: $task.val(),
        client_id: $client.val(),
        customer_id: $customer.val()
      },
      success: function(res){

          
        if(res.ok){
          const old = $ticket.val();
          $ticket.empty().append(new Option("{{ __('(None)') }}",""));
          res.items.forEach(it => $ticket.append(new Option(it.text, it.id)));
          if (old && $ticket.find('option[value="'+old+'"]').length) $ticket.val(old).trigger('change');
          else $ticket.val("").trigger('change');
        }
      }
    });
  }

  $type.on('change', reloadTickets);
  $project.on('change', reloadTickets);
  $task.on('change', reloadTickets);
  $client.on('change', reloadTickets);
  $customer.on('change', reloadTickets);

  // primera carga
  setTimeout(reloadTickets, 200);
});
</script>

