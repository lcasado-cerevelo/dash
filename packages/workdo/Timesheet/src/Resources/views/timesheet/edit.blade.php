<input type="hidden" name="submit_mode" id="submit_mode" value="update_and_close">

{{ Form::model($timesheet, [
  'route'  => ['timesheet.update', $timesheet->id],
  'method' => 'PUT',
  'id'     => 'timesheet-form',
  'class'  => 'needs-validation '.($errors->any()?'was-validated':''),
  'novalidate'
]) }}
@php($date = $date ?? \Carbon\Carbon::now()->toDateString())
<div class="modal-body">
  <div class="row">
    <div class="col-md-12">
      <div class="d-flex radio-check">
        @if (module_is_active('Hrm'))
        <div class="form-check form-check-inline form-group col-md-4">
          <input type="radio" id="clockin" value="Clock in/Clock out" name="type"
                 class="form-check-input code"
                 {{ old('type', $timesheet->type ?? ($prefill['type'] ?? '')) === 'Clock in/Clock out' ? 'checked' : '' }}>
          <label class="custom-control-label" for="clockin">{{ __('Clock In/Clock Out Wise') }}</label>
        </div>
        @endif

        @if (module_is_active('Taskly'))
        <div class="form-check form-check-inline form-group col-lg-3">
          <input type="radio" id="project" value="Project" name="type"
                 class="form-check-input code"
                 {{ old('type', $timesheet->type ?? ($prefill['type'] ?? '')) === 'Project' ? 'checked' : '' }}>
          <label class="custom-control-label" for="project">{{ __('Project Wise') }}</label>
        </div>
        @endif

        <div class="form-check form-check-inline form-group col-lg-3">
          <input type="radio" id="manually" value="Manually" name="type"
                 class="form-check-input code"
                 {{-- acepta ambos alias para compatibilidad --}}
                 {{ in_array(old('type', $timesheet->type ?? ($prefill['type'] ?? '')), ['Manually','Service Wise']) ? 'checked' : '' }}>
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
          old('date', $timesheet->date ?? $date),
          ['class'=>'form-control date','required'=>'required','max'=>\Carbon\Carbon::now()->toDateString(),'id'=>'date-input']
        ) }}
      </div>
    </div>

    {{-- Tiempos desde/hasta (si no guardas en DB, déjalos vacíos) --}}
    <div class="col-md-3">
      <div class="form-group">
        <label class="col-form-label">{{ __('From time') }}</label>
        <input type="time" name="from_time" id="from_time"
               class="form-control @error('duration') is-invalid @enderror" step="60"
               value="{{ old('from_time', $timesheet->from_time ?? '') }}">
        {!! $errors->first('from_time', '<div class="invalid-feedback d-block">:message</div>') !!}
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-group">
        <label class="col-form-label">{{ __('To time') }}</label>
        <input type="time" name="to_time" id="to_time"
               class="form-control @error('duration') is-invalid @enderror" step="60"
               value="{{ old('to_time', $timesheet->to_time ?? '') }}">
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
          value="{{ old('duration', sprintf('%02d:%02d', (int)$timesheet->hours, (int)$timesheet->minutes)) }}"
        >
        {!! $errors->first('duration', '<div class="invalid-feedback d-block">:message</div>') !!}
      </div>
    </div>

    @if (module_is_active('Taskly'))
      <div class="col-md-6 project_div d-none">
        <div class="form-group">
          {{ Form::label('project_id', __('Project'), ['class' => 'col-form-label']) }}<x-required></x-required>
          {{ Form::select('project_id', $project, old('project_id', $timesheet->project_id), [
              'class' => 'form-control get_tax multi-select',
              'id'    => 'project_id',
              'placeholder' => __('- Select project -')
          ]) }}
        </div>
      </div>

      {{-- Project Wise: Client derivado del Project --}}
      <div class="col-md-6 project_client_div d-none">
        <div class="form-group">
          {{ Form::label('customer_client_id', __('Client'), ['class' => 'col-form-label']) }}<x-required></x-required>
          {{ Form::select('customer_client_id', [],
              old('customer_client_id', $timesheet->customer_client_id),
              ['class' => 'form-control', 'id' => 'project_customer_client_id','placeholder' => __('- Select Client -')]) }}
        </div>
      </div>

      <div class="col-md-6 project_div d-none">
        <div class="form-group">
          {{ Form::label('task_id', __('Task'), ['class' => 'col-form-label']) }}<x-required></x-required>
          {{ Form::select('task_id', $task ?? [],
              old('task_id', $timesheet->task_id),
              ['class' => 'form-control', 'id'=>'task_id', 'placeholder' => __('- Select Task -')]) }}
        </div>
      </div>
    @endif

    {{-- Service Wise (Manually): Customer + Client --}}
    <div class="col-md-6 manually_div d-none">
      <div class="form-group">
        {{ Form::label('customer_id', __('Customer'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::select('customer_id', $customers ?? [],
            old('customer_id', $timesheet->customer_id),
            ['class' => 'form-control', 'id' => 'customer_id','placeholder' => __('- Select Customer -')]) }}
      </div>
    </div>
    <div class="col-md-6 manually_div d-none">
      <div class="form-group">
        {{ Form::label('customer_client_id', __('Client'), ['class' => 'col-form-label']) }}<x-required></x-required>
        {{ Form::select('customer_client_id', [],
            old('customer_client_id', $timesheet->customer_client_id),
            ['class' => 'form-control', 'id' => 'customer_client_id','placeholder' => __('- Select Client -')]) }}
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

    {{-- Description --}}
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

    {{-- Notes --}}
    <div class="col-md-12">
      <div class="form-group">
        {{ Form::label('notes', __('Notes'), ['class' => 'col-form-label']) }}
        {{ Form::textarea('notes', old('notes', $timesheet->notes ?? ''), ['class' => 'form-control notes','rows'=>'3','placeholder'=>__('Enter Notes')]) }}
      </div>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
  <button type="button" id="btn-save-close" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="{{ __('Update') }}">
    {{ __('Update') }}
  </button>
</div>
{{ Form::close() }}

<script>
$(function(){

  const $dur   = $('#duration');
  const $form  = $('#timesheet-form');
  const STORE_URL = @json(route('timesheet.update', $timesheet->id));

  // === NO forzar primer radio (evita pisar el tipo real) ===
  // $(".code").first().trigger('click'); // ← QUITADO

  // ==== helpers ya existentes (no modificados) ====
  function pad2(n){ return String(n).padStart(2,'0'); }
  function roundUpQuarterCap(hhmm){
    let [h,m] = hhmm.split(':').map(Number);
    let mm = Math.ceil(m/15)*15;
    h += Math.floor(mm/60);
    mm = mm % 60;
    if(h >= 24){ h = 23; mm = 59; }
    return `${pad2(h)}:${pad2(mm)}`;
  }
  function toMin(hhmm){ const [h,m]=hhmm.split(':').map(Number); return h*60+m; }
  function diffSameDay(from, to){
    const a = toMin(from), b = toMin(to);
    if (b <= a) return null;
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
        toastrs('Error', "{{ __('The From time must be earlier than the To time (same day).') }}", 'error');
        $dur.val('').prop('disabled', false);
        return;
      }
      $dur.val(roundUpQuarterCap(dur)).prop('disabled', true);
    } else {
      $dur.prop('disabled', false);
    }
  }
  $('#from_time,#to_time').on('change blur', computeFromTimes);

  // ====== toggles/loads existentes (sin cambiar lógica) ======
  const CSRF  = @json(csrf_token());
  const URL_BY_PROJECT  = @json(route('timesheet.clients.by-project'));
  const URL_BY_CUSTOMER = @json(route('timesheet.clients.by-customer'));
  const URL_GET_TASKS   = @json(route('gettask'));

  const $ccProject = $('#project_customer_client_id');
  const $ccManual  = $('#customer_client_id');

  const req = (sel, on) => $(sel).prop('required', !!on);
  const ena = (sel, on) => $(sel).prop('disabled', !on);

  function toggleType(type){
    if(type==='Project'){
      $(".project_div, .project_client_div").removeClass('d-none');
      $(".manually_div").addClass('d-none');
      req('#project_id', true); req('#task_id', true); req('#project_customer_client_id', true);
      req('#customer_id', false); req('#customer_client_id', false);
      ena('#project_id', true); ena('#task_id', true); ena('#project_customer_client_id', true);
      ena('#customer_id', false); ena('#customer_client_id', false);
    }
    else if(type==='Manually' || type==='Service Wise'){
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
    $el.prop('disabled', true).empty().append(`<option value="">${placeholder}</option>`);
    if (data) Object.entries(data).forEach(([id, name]) => $el.append(`<option value="${id}">${name}</option>`));
    $el.prop('disabled', false);
  }

  $(document).off('change', '#customer_id').on('change', '#customer_id', function () {
    const id = this.value;
    fillSelect($ccManual, null, `@lang('- Select Client -')`);
    if (!id) return;
    $.get(URL_BY_CUSTOMER, { customer_id: id })
      .done(data => fillSelect($ccManual, data, `@lang('- Select Client -')`))
      .fail(() => toastrs('Error', `@lang('Could not load Clients')`, 'error'));
  });

  $(document).off('change', '#project_id').on('change', '#project_id', function () {
    const id = this.value;
    const $task = $('#task_id');
    fillSelect($ccProject, null, `@lang('- Select Client -')`);
    $task.prop('disabled', true).find('option:not(:first)').remove();
    if (!id) return;

    $.post(URL_GET_TASKS, { project_id: id, _token: CSRF })
      .done(data => {
        $task.prop('disabled', false);
        Object.entries(data || {}).forEach(([tid, title]) => $task.append(`<option value="${tid}">${title}</option>`));
      })
      .fail(() => {
        $task.prop('disabled', false);
        toastrs('Error', `@lang('Could not load Tasks')`, 'error');
      });

    $.get(URL_BY_PROJECT, { project_id: id })
      .done(data => fillSelect($ccProject, data, `@lang('- Select Client -')`))
      .fail(() => toastrs('Error', `@lang('Could not load Clients')`, 'error'));
  });

  // ====== PRE-FILL desde el servidor ======
  const initialType   = @json(old('type', $timesheet->type ?? ($prefill['type'] ?? '')));
  const initialProj   = @json(old('project_id', $timesheet->project_id));
  const initialTask   = @json(old('task_id', $timesheet->task_id));
  const initialCust   = @json(old('customer_id', $timesheet->customer_id));
  const initialClient = @json(old('customer_client_id', $timesheet->customer_client_id));

  // marca el radio correcto y muestra secciones
  if (initialType) {
    // compat alias
    const radioVal = (initialType === 'Service Wise') ? 'Manually' : initialType;
    $(`input[name="type"][value="${radioVal}"]`).prop('checked', true);
    toggleType(initialType);
  }

  // Project wise: cargar dependientes y setear valores
  if (initialType === 'Project' && initialProj) {
    $('#project_id').val(initialProj).trigger('change');
    // cuando terminen las 2 llamadas AJAX de arriba
    $(document).one('ajaxStop', function(){
      if (initialTask)   $('#task_id').val(initialTask);
      if (initialClient) $('#project_customer_client_id').val(initialClient);
    });
  }

  // Service wise: cargar clientes por customer y setear
  if ((initialType === 'Service Wise' || initialType === 'Manually') && initialCust) {
    $('#customer_id').val(initialCust).trigger('change');
    $(document).one('ajaxStop', function(){
      if (initialClient) $('#customer_client_id').val(initialClient);
    });
  }

  // ==== resto de tu JS tal cual ====

  function updateRequirements(){
    const f = ($('#from_time').val()||'').trim();
    const t = ($('#to_time').val()||'').trim();
    $('#duration').prop('required', !(f && t));
  }
  function markInvalid($el, msg){
    $el.addClass('is-invalid').removeClass('is-valid');
    $el.nextAll('.invalid-feedback').first().remove();
    if (msg) $el.after('<div class="invalid-feedback d-block">'+msg+'</div>');
    $form.addClass('was-validated');
  }
  function clearInvalid($el){
    $el[0].setCustomValidity('');
    $el.removeClass('is-invalid');
    $el.nextAll('.invalid-feedback').first().remove();
  }
  $('#duration,#from_time,#to_time').on('input change', function(){ clearInvalid($(this)); });
  $('#from_time,#to_time').on('input change', updateRequirements);
  updateRequirements();

  $(document).on('change',"[name='type']",function(){
    const type = $(this).val();
    toggleType(type);
    if(type==='Project' && $('#project_id').val()) $('#project_id').trigger('change');
    if((type==='Manually' || type==='Service Wise') && $('#customer_id').val()) $('#customer_id').trigger('change');
  });

  // ===== Tickets: se recargan con el contexto actual =====
  const $type = $('input[name="type"]');
  const $project = $('#project_id');
  const $task = $('#task_id');
  const $client = $('#client_id');
  const $customer = $('#customer_id');
  const $ticket = $('#ticket_id');

  function reloadTickets(){
    const typeRadio = $('input[name="type"]:checked').val();
    const normalized = (typeRadio === 'Manually') ? 'Service Wise' : typeRadio;
    if(!normalized) return;
    $.ajax({
      url: "{{ route('timesheet.tickets.for') }}",
      data: {
        type: normalized,
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
  // primera carga (tras prefill)
  setTimeout(reloadTickets, 250);

  // === Tu submit / tooltips tal cual ===
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    if (!bootstrap.Tooltip.getInstance(el)) new bootstrap.Tooltip(el);
  });
  $('#btn-save-close').on('click', function(){
    $('#submit_mode').val('update_and_close');
    $form.trigger('submit');
  });

  function validateDescription() {
    const $desc = $('[name="description"]:visible').first();
    const v = ($desc.val() || '').trim();
    if (!v) { toastrs('Error', '{{ __("Description is required.") }}', 'error'); $desc.focus(); return false; }
    return true;
  }

  $form.on('submit', function(e){
    if (!validateDescription()){
      e.preventDefault(); e.stopImmediatePropagation();
      toastrs('Error', "{{ __('Description is required.') }}", 'error');
      return false;
    }
    // tu bloque AJAX/validación original aquí (sin cambios)
    e.preventDefault(); e.stopImmediatePropagation();
    $form.find('.invalid-feedback').remove();
    $form.find('.is-invalid').removeClass('is-invalid');

    $.ajax({
      url: STORE_URL,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': @json(csrf_token()),
        'X-Requested-With': 'XMLHttpRequest'
      },
      data: $form.serialize(), // (si usas AJAX PUT, puedes añadir &_method=PUT)
      success: function(resp){
        toastrs('Success', (resp && resp.message) || "{{ __('Timesheet successfully updated.') }}", 'success');
        // opcional cerrar modal/recargar tabla, no lo toco por tu pedido
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

  function renderErrors(errors){
    for (const [field, msgs] of Object.entries(errors || {})) {
      const $el = $('[name="'+field+'"]');
      if ($el.length) markInvalid($el, (msgs && msgs[0]) || 'Invalid');
    }
  }

  function resetFormForNew(){ /* sin uso en edit; lo dejo igual */ }

});
</script>
