@extends('layouts.main')

@section('page-title')
    {{ __('Create Ticket') }}
@endsection

@section('page-breadcrumb')
    <a href="{{ route('helpdesk.index') }}">{{ __('Tickets') }}</a>
@endsection
@push('css')
    <link href="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush
@section('content')
    <form action="{{ route('helpdesk.store') }}" class="mt-3 needs-validation" method="post" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="row">
            <div class="col-md-12 col-xs-12">
                <div class="card">
                    <div class="card-body">
                        @if(Auth::user()->type == 'super admin')
                            <div class="row">
                                @php
                                    $name =  'Customers';
                                @endphp
                                <div class="form-group col-md-6" id="customname">
                                    <label class="require form-label">{{ $name}}</label><x-required></x-required>
                                    <select  class="form-control select_person_email" name="name"  {{ !empty($errors->first('name')) ? 'is-invalid' : '' }} required="">
                                        <option value="">{{ __('Select User') }}</option>
                                        @foreach ($users as $key=>$value)
                                            <option value="{{ $key }}">{{ $value }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ $errors->first('name') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="require form-label">{{ __('Email') }}</label><x-required></x-required>
                                    <input class="form-control emailAddressField {{ !empty($errors->first('email')) ? 'is-invalid' : '' }}"
                                        type="email" name="email" required="" placeholder="{{ __('Email') }}"  readonly >
                                    <div class="invalid-feedback">
                                        {{ $errors->first('email') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label class="require form-label">{{ __('Subject') }}</label><x-required></x-required>
                                <input class="form-control {{ !empty($errors->first('subject')) ? 'is-invalid' : '' }}"
                                    type="text" name="subject" required="" placeholder="{{ __('Subject') }}">
                                <div class="invalid-feedback">
                                    {{ $errors->first('subject') }}
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="require form-label">{{ __('Category') }}</label><x-required></x-required>
                                <select class="form-control {{ !empty($errors->first('category')) ? 'is-invalid' : '' }}"
                                    name="category" required="" id="category">
                                    <option value="">{{ __('— Select Category —') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                    {{ $errors->first('category') }}
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="require form-label">{{ __('Status') }}</label><x-required></x-required>
                                <select class="form-control {{ !empty($errors->first('status')) ? 'is-invalid' : '' }}"
                                    name="status" required="" id="status">
                                  <option value="New" {{ old('status','New')=='New' ? 'selected' : '' }}>New</option>
                                  <option value="In Progress" {{ old('status')=='In Progress' ? 'selected' : '' }}>In Progress</option>
                                  <option value="On Hold" {{ old('status')=='On Hold' ? 'selected' : '' }}>On Hold</option>
                                  <option value="Validation" {{ old('status')=='Validation' ? 'selected' : '' }}>Validation</option>
                                  <option value="Closed" {{ old('status')=='Closed' ? 'selected' : '' }}>Closed</option>                                    
                                </select>
                                <div class="invalid-feedback">
                                    {{ $errors->first('status') }}
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                              <label class="require form-label">{{ __('Priority') }}</label><x-required></x-required>
                              @php
                                $priorities = [
                                  1 => 'Very Low',
                                  2 => 'Low',
                                  3 => 'Regular',
                                  4 => 'High',
                                  5 => 'Very High'
                                ];
                              @endphp
                              <select name="priority" class="form-control" required>
                                @foreach($priorities as $value => $label)
                                  <option value="{{ $value }}" {{ old('priority',3)==$value ? 'selected':'' }}>
                                    {{ $label }}
                                  </option>
                                @endforeach
                              </select>
                            </div>

                            
                            @php
                                // $taskProjects: array con id,label,project_id,customer_id
                                // $customers: lista customers
                                // $customerClients: lista dependiente (solo en edit)
                                $selectedTaskId     = old('task_id', '');
                                $selectedCustomerId = old('customer_id', '');
                                $selectedClientId   = old('customer_client_id', '');
                            @endphp 
                            <div class="form-group col-md-6">
                                <label class="form-label">{{ __('Task / Project') }}</label>
                                <select name="task_id" id="task_project_select" class="form-control">
                                    <option value="">— Select Task / Project —</option>
                                    @foreach ($taskProjects as $tp)
                                        <option value="{{ $tp['id'] }}"
                                                data-project-id="{{ $tp['project_id'] ?? '' }}"
                                                data-customer-id="{{ $tp['customer_id'] ?? '' }}"
                                                @selected((string)$selectedTaskId === (string)$tp['id'])>
                                            {{ $tp['label'] }}
                                        </option>
                                    @endforeach
                                </select>

                            </div>
                            
                            <div class="form-group col-md-6">
                                <label class="form-label">{{ __('Customer') }}</label>
                                <select name="customer_id" id="customer_select" class="form-control">
                                    <option value="">— Select Customer —</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c->id }}" @selected((string)$selectedCustomerId === (string)$c->id)>
                                            {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="customer_id" id="customer_id_hidden" value="{{ old('customer_id', $ticket->customer_id ?? '') }}">
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label class="form-label">{{ __('Customer Client') }}</label>
                                <select name="customer_client_id" id="customer_client_select" class="form-control">
                                    <option value="">— Select Client —</option>
                                    @forelse ($customerClients as $cc)
                                        <option value="{{ $cc->id }}" @selected((string)$selectedClientId === (string)$cc->id)>{{ $cc->name }}</option>
                                    @empty
                                        {{-- vacío si no hay customer seleccionado aún --}}
                                    @endforelse
                                </select>
                            </div>                            
                            @if(!empty($canAssign) && $canAssign)
                              <div class="form-group col-md-6">
                                <label class="form-label">{{ __('Assigned To') }}</label>
                                <select name="assigned_user_id" class="form-control">
                                  <option value="">{{ __('— Select User —') }}</option>
                                  @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}"
                                      {{ (string)($selectedAssigneeId ?? '') === (string)$u->id ? 'selected' : '' }}>
                                      {{ $u->name }}
                                    </option>
                                  @endforeach
                                </select>
                              </div>

                                <div class="form-group col-md-9">
                                    <label for="parent_ticket_id" class="form-label d-block">{{ __('Related Ticket') }}</label>
                                    <select name="parent_ticket_id" id="parent_ticket_id" class="form-control w-100">
                                        <option value="">{{ __('None') }}       </option>
                                        @foreach($parentTickets as $pt)
                                            <option value="{{ $pt['id'] }}"
                                                @selected((string) old('parent_ticket_id', $ticket->parent_ticket_id ?? '') === (string) $pt['id'])>
                                                {{ $pt['label'] }}
                                            </option>                                                
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-3 d-flex align-items-end">
                                    <a href="javascript:void(0);"
                                       id="view_parent_ticket_link"
                                       class="btn btn-outline-info w-100 disabled"
                                       data-bs-toggle="modal"
                                       data-bs-target="#parentTicketPreviewModal"
                                       aria-disabled="true">
                                        {{ __('View Ticket') }}
                                    </a>
                                </div>
                                <div class="form-group col-md-12">
                                    <label class="form-label">{{ __('Can View Ticket') }}</label>
                                    <select name="visible_to_user_ids[]" id="visible_to_user_ids" class="form-control" multiple>
                                        @foreach($assignableUsers as $au)
                                            <option value="{{ $au->id }}" @selected(in_array($au->id, old('visible_to_user_ids', [])))>
                                                {{ $au->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">{{ __('These users will only see the ticket and the View button.') }}</small>
                                </div>                              
                            @endif


                            <div class="form-group col-md-12">
                                <label class="require form-label">{{ __('Attachments') }}
                                    <small>({{ __('You can select multiple files') }})</small> </label>
                                <div class="choose-file">
                                    <label for="file" class="form-label d-block">
                                        <input type="file" name="attachments[]" id="file"
                                            class="form-control {{ $errors->has('attachments') ? ' is-invalid' : '' }}"
                                            multiple="" data-filename="multiple_file_selection"
                                            onchange="document.getElementById('blah').src = window.URL.createObjectURL(this.files[0])">
                                        <img src="" id="blah" width="20%" class="mt-3 d-none"/>
                                        <div class="invalid-feedback">
                                            {{ $errors->first('attachments.*') }}
                                        </div>
                                    </label>
                                </div>
                                <p class="multiple_file_selection mx-4"></p>
                            </div>

                            <div class="form-group col-md-12">
                                <label class="require form-label">{{ __('Description') }}</label>
                                <textarea name="description"
                                    class="form-control summernote  {{ !empty($errors->first('description')) ? 'is-invalid' : '' }}"
                                    id="help-desc"></textarea>
                                <div class="invalid-feedback">
                                    {{ $errors->first('description') }}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end text-end">
                            <a class="btn btn-secondary btn-submit"
                                href="{{ route('helpdesk.index') }}">{{ __('Cancel') }}</a>
                            <button class="btn btn-primary btn-submit ms-2" type="submit">{{ __('Create') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const parentTicketsMap = @json(collect($parentTickets)->keyBy('id'));
    </script>
    <script src="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.js') }}"></script>
    <script>
        const $taskSel     = $('#task_project_select');
        const $custSel     = $('#customer_select');
        const $clientSel   = $('#customer_client_select');    
        const ajaxClientsUrlBase = @json(route('helpdesk.ajax.customer-clients', ['customer' => '___ID___']));
        const allCustomerOptionsHtml = $custSel.html();
        
         function loadCustomerClients(customerId, selectedId) {
              if (!customerId) {
                  $clientSel.html('<option value="">— Select Client —</option>');
                  return;
              }
              const url = ajaxClientsUrlBase.replace('___ID___', customerId);
              $.getJSON(url, function(list){
                  let html = '<option value="">— Select Client —</option>';
                  $.each(list, function(_, item){
                      html += '<option value="'+ item.id +'">'+ item.name +'</option>';
                  });
                  $clientSel.html(html);
                  if (selectedId) $clientSel.val(String(selectedId));
              });
          }
        
        function onTaskChange() {
          const val = $taskSel.val();
        
          if (!val) {
            // Habilitar select => el hidden NO debe enviarse
            $custSel.prop('disabled', false).trigger('change');
            $('#customer_id_hidden').prop('disabled', true); // <- importante
            $custSel.html(allCustomerOptionsHtml);
            return;
          }
        
          const url = "{{ route('helpdesk.ajax.customers-by-task', ['taskId' => 'TASK_ID']) }}".replace('TASK_ID', val);
        
          $.getJSON(url, function(list){
            let html = '<option value="">— Select Customer —</option>';
            list.forEach(function(it){ html += '<option value="'+it.id+'">'+it.name+'</option>'; });
        
            if (list.length === 1) {
              // Autoselecciona y bloquea el select
              $custSel.html(html);
              $custSel.val(String(list[0].id)).prop('disabled', true).trigger('change');
        
              // Cuando el select está disabled, activamos el hidden para que se envíe
              $('#customer_id_hidden').val(String(list[0].id)).prop('disabled', false);
            } else {
              // Varios customers: habilita select y desactiva el hidden
              $custSel.html(allCustomerOptionsHtml);
              $custSel.prop('disabled', false).trigger('change');
              $('#customer_id_hidden').prop('disabled', true);
            }
          });
        }
        
        function onCustomerChange() {
          const custId = $custSel.val();
          // solo reflejamos el valor; quién se envía lo decide el 'disabled' de arriba
          $('#customer_id_hidden').val(custId || '');
          loadCustomerClients(custId, null);
        }

        
          // Bind
          $taskSel.on('change', onTaskChange);
          $custSel.on('change', onCustomerChange);  
          
        $(document).on('click', '#file', function() {
            $('#blah').removeClass('d-none');
        });

        $(document).on('change', '.select_person_email', function() {
            var userId = $(this).val();
            $.ajax({
                url: '{{ route('helpdesk-tickets.getuser') }}',
                type: 'POST',
                data: {
                    "user_id": userId,
                    "_token": "{{ csrf_token() }}",
                },
                success: function(data) {
                    if(data.email)
                    {
                        $('.emailAddressField').val(data.email);
                        $('.emailAddressField').prop('readonly', true);
                        $('.emailAddressField').css('background-color', '#e9ecef');
                    }else{
                        $('.emailAddressField').val('');
                        $('.emailAddressField').prop('readonly', false);
                        $('.emailAddressField').css('background-color', '');
                    }
                }
            });
        });
        $(function(){
              // Si ya hay task seleccionado, bloquea Customer
              onTaskChange();
        
              // Si ya hay customer seleccionado, precarga sus clients y selecciona el existente
              const presetCustomer = $custSel.val();
              const presetClient = @json(old('customer_client_id', ''));
              if (presetCustomer) {
                  loadCustomerClients(presetCustomer, presetClient);
              }
        });        
    </script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        const $parent = $('#parent_ticket_id');
        const $link = $('#view_parent_ticket_link');
        const $modalLabel = $('#parent_ticket_modal_label');
        const $modalCreatedBy = $('#parent_ticket_modal_created_by');
        const $modalDescription = $('#parent_ticket_modal_description');

        function updateParentTicketPreview() {
            const value = $parent.val();

            if (!value || !parentTicketsMap[value]) {
                $link.addClass('disabled').attr('aria-disabled', 'true');
                $modalLabel.text('---');
                $modalCreatedBy.text('---');
                $modalDescription.html('<em>--</em>');
                return;
            }

            const item = parentTicketsMap[value];

            $link.removeClass('disabled').attr('aria-disabled', 'false');
            $modalLabel.text(item.label || '---');
            $modalCreatedBy.text(item.created_by_name || '---');

            if (item.description && String(item.description).trim() !== '') {
                $modalDescription.html(item.description);
            } else {
                $modalDescription.html('<em>--</em>');
            }
        }

        if ($parent.length && window.jQuery && $.fn.select2) {
            $parent.select2({
                width: '100%',
                placeholder: 'Search ticket.',
                allowClear: true
            });
        }

        $parent.on('change', updateParentTicketPreview);

        $link.on('click', function (e) {
            if ($(this).hasClass('disabled')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        updateParentTicketPreview();
    });
</script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $('#visible_to_user_ids').length && $.fn.select2) {
            $('#visible_to_user_ids').select2({
                width: '100%',
                placeholder: 'Select users who can view the ticket'
            });
        }
    });
    </script>    

@endpush

<div class="modal fade" id="parentTicketPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Related Ticket Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <strong>{{ __('Ticket') }}:</strong>
                    <span id="parent_ticket_modal_label">---</span>
                </div>
                <div class="mb-2">
                    <strong>{{ __('Created By') }}:</strong>
                    <span id="parent_ticket_modal_created_by">---</span>
                </div>
                <div class="mb-2">
                    <strong>{{ __('Description') }}:</strong>
                </div>
                <div id="parent_ticket_modal_description" class="border rounded p-3 bg-light">
                    ---
                </div>
            </div>
        </div>
    </div>
</div>