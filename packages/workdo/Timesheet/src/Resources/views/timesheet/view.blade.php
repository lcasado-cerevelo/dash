{{-- PARCIAL PARA MODAL: NO layout, NO sections --}}

@php
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $me            = Auth::user();
  $isSuper       = $me && method_exists($me,'hasRole') ? $me->hasRole('super admin') : false;
  $isCustClient       = $me && method_exists($me,'hasRole') ? $me->hasRole('Customer') || $me->hasRole('Client') : false;
  $isVendorConsultant       = $me && method_exists($me,'hasRole') ? $me->hasRole('Vendor') || $me->hasRole('Consultant') : false;

  // permisos
  $canValidateRaw   = $isSuper || ($me && method_exists($me,'hasPermission') && $me->hasPermission('timesheet validate'));
  $canUnvalidateRaw = $isSuper || ($me && method_exists($me,'hasPermission') && $me->hasPermission('timesheet unvalidate'));
  $canBillRaw       = $isSuper || ($me && method_exists($me,'hasPermission') && $me->hasPermission('timesheet bill'));
  $canInvoiceRaw    = $isSuper || ($me && method_exists($me,'hasPermission') && $me->hasPermission('timesheet invoice'));

  // estados
  $isApproved   = (int)($ts->validated ?? 0) === 1;
  $isInvoiced   = (int)($ts->invoiced ?? 0) === 1;
  $isBilled   = (int)($ts->billed ?? 0) === 1;
  $isPaid   = (int)($ts->paid ?? 0) === 1;

  // botones finales (regla solicitada)
  $canApprove    = !$isApproved && $canValidateRaw;
  $canUnapprove  =  $isApproved && $canUnvalidateRaw && !$isInvoiced && !$isBilled && !$isPaid;
  $canBill       =  $isApproved && !$isBilled && !$isPaid && $canBillRaw;
  $canInvoice    =  $isApproved && !$isInvoiced & $canInvoiceRaw;

  $type = $ts->type ?? '';
@endphp

<div class="modal-body">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0">{{ __('Timesheet Details') }}</h5>
    <div class="d-flex gap-2">
      @isset($ts->validated)
        <span class="badge {{ $ts->validated ? 'bg-success' : 'bg-warning text-dark' }}">
          {{ $ts->validated ? __('Approved') : __('Pending') }}
        </span>
      @endisset
      @unless($isCustClient)
      @isset($ts->billed)
        <span class="badge {{ $ts->billed ? 'bg-info' : 'bg-secondary' }}">
          {{ $ts->billed ? __('Billed') : __('Not billed') }}
        </span>
      @endisset
      @unless($isVendorConsultant)
      @isset($ts->paid)
        <span class="badge {{ $ts->paid ? 'bg-primary' : 'bg-secondary' }}">
          {{ $ts->paid ? __('Paid') : __('Unpaid') }}
        </span>
      @endisset
      @endunless
      @endunless
    </div>
  </div>

  {{-- Encabezado principal --}}
  <div class="row mb-3">
    <div class="col-md-6">
      <strong>{{ __('Employee:') }}</strong>
      {{ optional($ts->user)->name ?? '-' }}
    </div>
    <div class="col-md-6">
      <strong>{{ __('Date:') }}</strong>
      {{ Carbon::parse($ts->date)->format('Y-m-d') }}
      &nbsp; <strong>{{ __('From:') }}</strong> {{ $ts->from_time ?: '-' }}
      &nbsp; <strong>{{ __('To:') }}</strong>   {{ $ts->to_time   ?: '-' }}
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <strong>{{ __('Total (hh:mm):') }}</strong>
      {{ sprintf('%02d:%02d', (int)$ts->hours, (int)$ts->minutes) }}
    </div>
    <div class="col-md-6">
      <strong>{{ __('Type:') }}</strong> {{ $ts->type ?? '-' }}
    </div>
  </div>

  @if(strcasecmp($type,'Project')===0)
    {{-- Project Wise --}}
    <div class="row mb-3">
      <div class="col-md-6">
        <strong>{{ __('Project:') }}</strong> {{ optional($ts->project)->name ?? '—' }}
      </div>
      <div class="col-md-6">
        <strong>{{ __('Task:') }}</strong>
        {{ optional($ts->task)->title ?? optional($ts->task)->name ?? '—' }}
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-12">
        <strong>{{ __('Ticket:') }}</strong>
        @php
          $ticketNum = optional($ts->ticket)->number ?? null;
          $ticketSub = optional($ts->ticket)->subject ?? null;
        @endphp
        @if(!empty($ts->ticket))
          #{{ $ticketNum ?? $ts->ticket->id }} — {{ Str::limit($ticketSub ?? '', 120) }}
        @else
          —
        @endif
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <strong>{{ __('Client:') }}</strong> {{ optional($ts->customerClient)->name ?? '—' }}
      </div>
      <div class="col-md-6">
        <strong>{{ __('Customer:') }}</strong> {{ optional($ts->customer)->name ?? '—' }}
      </div>
    </div>

  @elseif(strcasecmp($type,'Manually')===0 || strcasecmp($type,'Service Wise')===0)
    {{-- Service/Manually --}}
    <div class="row mb-3">
      <div class="col-md-6">
        <strong>{{ __('Customer:') }}</strong> {{ optional($ts->customer)->name ?? '—' }}
      </div>
      <div class="col-md-6">
        <strong>{{ __('Client:') }}</strong> {{ optional($ts->customerClient)->name ?? '—' }}
      </div>
    </div>

  @else
    {{-- Clock in / Clock out --}}
    <div class="row mb-3">
      <div class="col-md-12">
        <em class="text-muted">{{ __('Clock In/Clock Out entry') }}</em>
      </div>
    </div>
  @endif

  {{-- Descripción y Notas --}}
  <div class="mb-3">
    <strong>{{ __('Description:') }}</strong><br>
    {!! nl2br(e($ts->description ?? '')) ?: '—' !!}
  </div>

  <div class="mb-3">
    <strong>{{ __('Notes:') }}</strong><br>
    {!! nl2br(e($ts->notes ?? '')) ?: '—' !!}
  </div>

  <hr>

  <h6 class="mt-3">{{ __('Approval history') }}</h6>
  <table class="table table-sm">
    <thead>
      <tr>
        <th>{{ __('When') }}</th>
        <th>{{ __('Action') }}</th>
        <th>{{ __('By') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($ts->approvals()->latest()->limit(50)->get() as $ap)
        <tr>
          <td>{{ $ap->created_at->format('Y-m-d H:i:s') }}</td>
          <td class="text-capitalize">{{ $ap->action }}</td>
          <td>{{ optional($ap->user)->name ?? ('#'.$ap->user_id) }}</td>
        </tr>
      @empty
        <tr><td colspan="3"><em>{{ __('No approval activity yet.') }}</em></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>

  @if($canUnapprove)
    {{-- Requisito: si está aprobado y tienes permiso (o eres super admin), mostrar Unapprove --}}
    <a href="#" id="btn-unapprove-one" class="btn btn-warning">
      <i class="ti ti-circle-x"></i> {{ __('Unapprove') }}
    </a>
  @endif

  @if($canApprove)
    <a href="#" id="btn-approve-one" class="btn btn-success">
      <i class="ti ti-circle-check"></i> {{ __('Approve') }}
    </a>
  @endif
  @if($canBill)
    <a href="#" id="btn-bill-one" class="btn btn-success">
      <i class="ti ti-receipt"></i> {{ __('Bill') }}
    </a>
  @endif
  @if($canInvoice)
    <a href="#" id="btn-invoice-one" class="btn btn-success">
      <i class="ti ti-file-invoice"></i> {{ __('Invoice') }}
    </a>
  @endif

</div>

{{-- Sub-modal de contraseña --}}
<div class="modal fade" id="tsPasswordModalOne" tabindex="-1" aria-labelledby="tsPasswordModalOneLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="tsPasswordFormOne" class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="tsPasswordModalOneLabel">{{ __('Confirm action') }}</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">
          <h4><strong>Enter your password to confirm the approval/unapproval.</strong></h4><br>
          Make sure you are performing the correct action, as it is not reversible.
        </p>
        <div class="mb-3">
          <label for="tsPasswordInputOne" class="form-label">{{ __('Password') }}</label>
          <input type="password" class="form-control" id="tsPasswordInputOne" name="password" required autocomplete="current-password">
          <div class="invalid-feedback">{{ __('Password is required') }}</div>
        </div>
        <input type="hidden" id="tsActionOne" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-lock"></i> {{ __('Confirm') }}
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const $ = window.jQuery;
  const pwdModalEl = document.getElementById('tsPasswordModalOne');
  if(!pwdModalEl) return;
  const pwdModal = new bootstrap.Modal(pwdModalEl);
  const $pwd = $('#tsPasswordInputOne');
  let action = ''; // 'validate' | 'unvalidate'

  function updateOneModalUI(action){
    const isApprove = action === 'validate';
    document.getElementById('tsPasswordModalOneLabel').textContent =
      isApprove ? 'Confirm approval' : 'Confirm unapproval';
    const strongEl = document.querySelector('#tsPasswordModalOne .modal-body strong');
    if (strongEl) strongEl.textContent = isApprove
      ? 'Enter your password to confirm the approval.'
      : 'Enter your password to confirm the unapproval.';
    const btn = document.querySelector('#tsPasswordModalOne .modal-footer .btn.btn-primary');
    if (btn){
      btn.textContent = isApprove ? 'Approve' : 'Unapprove';
      btn.classList.remove('btn-success','btn-warning','btn-primary');
      btn.classList.add(isApprove ? 'btn-success' : 'btn-warning');
    }
  }

  $(document).on('click','#btn-approve-one', function(e){
    e.preventDefault();
    action = 'validate';
    $('#tsActionOne').val('validate');
    $pwd.val('');
    updateOneModalUI('validate');
    pwdModal.show();
    setTimeout(()=> $pwd.trigger('focus'), 120);
  });

  $(document).on('click','#btn-unapprove-one', function(e){
    e.preventDefault();
    action = 'unvalidate';
    $('#tsActionOne').val('unvalidate');
    $pwd.val('');
    updateOneModalUI('unvalidate');
    pwdModal.show();
    setTimeout(()=> $pwd.trigger('focus'), 120);
  });

  $('#tsPasswordFormOne').on('submit', function(e){
    e.preventDefault();
    const pwd = $.trim($('#tsPasswordInputOne').val() || '');
    if (!pwd) { $('#tsPasswordInputOne').addClass('is-invalid'); return; }
    $('#tsPasswordInputOne').removeClass('is-invalid');

    const verifyUrl = "{{ route('timesheet.verify-password') }}";
    const token = "{{ csrf_token() }}";

    $.post({ url: verifyUrl, data: { password: pwd, _token: token } })
      .done(function(){
        const url = (action === 'validate')
          ? "{{ route('timesheet.validate', ['timesheet' => $ts->id]) }}"
          : "{{ route('timesheet.unvalidate', ['timesheet' => $ts->id]) }}";

        $.post({
          url: url,
          data: { password: pwd, _token: token },
          success: function(){
            const ins = bootstrap.Modal.getInstance(pwdModalEl);
            if(ins) ins.hide();
            window.location.reload();
          },
          error: function(xhr){
            const msg = xhr.responseJSON?.message || 'Error';
            toastrs('Error', msg, 'error');
          }
        });
      })
      .fail(function(xhr){
        const msg = xhr.responseJSON?.message || 'Incorrect password';
        toastrs('Error', msg, 'error');
        $('#tsPasswordInputOne').val('').trigger('focus');
      });
  });

  pwdModalEl.addEventListener('hidden.bs.modal', () => {
    action = '';
    $pwd.val('').removeClass('is-invalid');
  });
})();
</script>
