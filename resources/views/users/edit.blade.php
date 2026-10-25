@push('css')
<style>
    #ticket-mail-settings-section.opacity-50 {
        opacity: .55;
        pointer-events: none;
    }
</style>
@endpush
{{Form::model($user,array('route' => array('users.update', $user->id), 'method' => 'PUT','class'=>'needs-validation','novalidate')) }}
    <div class="modal-body">
        <div class="row">
            @if(Auth::user()->type == 'super admin')
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('name',__('Name'),['class'=>'form-label']) }}<x-required></x-required>
                        {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter Customer Name'),'required'=>'required'))}}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                        {{Form::email('email',null,array('class'=>'form-control','placeholder'=>__('Enter Customer Email'),'required'=>'required'))}}
                    </div>
                </div>
            @else
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('name',__('Name'),['class'=>'form-label']) }}<x-required></x-required>
                        {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter User Name'),'required'=>'required'))}}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                        {{Form::email('email',null,array('class'=>'form-control','placeholder'=>__('Enter User Email'),'required'=>'required'))}}
                    </div>
                </div>
            @endif
            <x-mobile value="{{ !empty($user->mobile_no) ? $user->mobile_no : null }}"></x-mobile>
            @if(Auth::user()->type == 'super admin' || \Laratrust::hasRole('user create') || \Laratrust::hasPermission('user edit'))
                <div class="row">
                  <div class="col-md-12">
                    <label class="form-label">{{ __('Customers') }}</label>
                    <select name="customer_ids[]" id="user-customers" class="form-control" multiple data-placeholder="{{ __('- Select customers -') }}">
                      @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ in_array($c->id, $selectedCustomers) ? 'selected' : '' }}>
                          {{ $c->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                
                  <div class="col-md-12">
                    <label class="form-label">{{ __('Customer Clients') }}</label>
                    <select name="customer_client_ids[]" id="user-customer-clients" class="form-control" multiple data-placeholder="{{ __('- Select customer clients -') }}">
                      @foreach($customerClients as $cc)
                        <option value="{{ $cc->id }}" {{ in_array($cc->id, $selectedCustomerClients) ? 'selected' : '' }}>
                          {{ $cc->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                
                  <div class="col-md-12">
                    <label class="form-label">{{ __('Projects') }}</label>
                    <select name="project_ids[]" id="user-projects" class="form-control" multiple data-placeholder="{{ __('- Select projects -') }}">
                      @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ in_array($p->id, $selectedProjects) ? 'selected' : '' }}>
                          {{ $p->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div> 
                
<hr class="my-4">

<div class="col-md-12">
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">{{ __('Ticket Creation by Email') }}</h6>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="allow_send_tickets_by_mail" name="allow_send_tickets_by_mail" value="1"
                    {{ !empty($allowSendTicketsByMail) ? 'checked' : '' }}>
                <label class="form-check-label ms-2" for="allow_send_tickets_by_mail">
                    {{ __('Allow Ticket Creation by Email') }}
                </label>
            </div>
        </div>

        <div class="card-body">
            <div class="row" id="ticket-mail-settings-section">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Mailbox') }}</label>
                        <select name="ticket_mailbox_id" id="ticket_mailbox_id" class="form-control ticket-mail-field">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($mailboxes as $mailbox)
                                <option value="{{ $mailbox->id }}"
                                    {{ (string) old('ticket_mailbox_id', $ticketMailSettings['mailbox_id'] ?? '') === (string) $mailbox->id ? 'selected' : '' }}>
                                    {{ $mailbox->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Customer Client') }}</label>
                        <select name="ticket_customer_client_id" id="ticket_customer_client_id" class="form-control ticket-mail-field">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($customerClients as $client)
                                <option value="{{ $client->id }}"
                                    data-customer-id="{{ $client->customer_id }}"
                                    {{ (string) old('ticket_customer_client_id', $ticketMailSettings['customer_client_id'] ?? '') === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Task / Project') }}</label>
                        <select name="ticket_task_id" id="ticket_task_id" class="form-control ticket-mail-field">
                            <option value="">{{ __('— Select Task / Project —') }}</option>
                            @foreach($ticketTasks as $taskProject)
                                <option value="{{ $taskProject->id }}"
                                    data-project-id="{{ $taskProject->project_id }}"
                                    data-customer-ids="{{ $taskProject->customer_ids }}"
                                    {{ (string) old('ticket_task_id', $ticketMailSettings['task_id'] ?? '') === (string) $taskProject->id ? 'selected' : '' }}>
                                    {{ $taskProject->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Category') }}</label>
                        <select name="ticket_category_id" id="ticket_category_id" class="form-control ticket-mail-field">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($ticketCategories as $category)
                                <option value="{{ $category->id }}"
                                    {{ (string) old('ticket_category_id', $ticketMailSettings['category_id'] ?? '') === (string) $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Priority') }}</label>
                        <select name="ticket_priority" id="ticket_priority" class="form-control ticket-mail-field">
                            <option value="">{{ __('Select') }}</option>
                            <option value="1" {{ (string) old('ticket_priority', $ticketMailSettings['priority'] ?? '') === '1' ? 'selected' : '' }}>Very Low</option>
                            <option value="2" {{ (string) old('ticket_priority', $ticketMailSettings['priority'] ?? '') === '2' ? 'selected' : '' }}>Low</option>
                            <option value="3" {{ (string) old('ticket_priority', $ticketMailSettings['priority'] ?? '') === '3' ? 'selected' : '' }}>Regular</option>
                            <option value="4" {{ (string) old('ticket_priority', $ticketMailSettings['priority'] ?? '') === '4' ? 'selected' : '' }}>High</option>
                            <option value="5" {{ (string) old('ticket_priority', $ticketMailSettings['priority'] ?? '') === '5' ? 'selected' : '' }}>Very High</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Assign To') }}</label>
                        <select name="ticket_assign_to" id="ticket_assign_to" class="form-control ticket-mail-field">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($assignableUsers as $assignUser)
                                <option value="{{ $assignUser->id }}"
                                    {{ (string) old('ticket_assign_to', $ticketMailSettings['assign_to'] ?? '') === (string) $assignUser->id ? 'selected' : '' }}>
                                    {{ $assignUser->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">{{ __('Order') }}</label>
                        <input type="number" name="ticket_sort_order" id="ticket_sort_order" class="form-control ticket-mail-field"
                            value="{{ old('ticket_sort_order', $ticketMailSettings['sort_order'] ?? 10) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                <div class="mt-3">
                    <button type="button"
                            class="btn btn-sm btn-primary"
                            onclick="document.getElementById('grant-timesheet-override-form').submit();">
                        Grant temporary access to enter timesheets
                    </button>
                </div>                
            @endif            
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Update'),array('class'=>'btn  btn-primary'))}}
    </div>
{{Form::close()}}
    <form id="grant-timesheet-override-form"
          method="POST"
          action="{{ route('admin.users.timesheet-override', $user->id) }}"
          style="display:none;">
        @csrf
    </form>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const $ = window.jQuery;
  if ($ && $.fn && $.fn.select2) {
    $('#user-customers').select2({ width:'100%', allowClear:true, placeholder: $('#user-customers').data('placeholder') });
    $('#user-customer-clients').select2({ width:'100%', allowClear:true, placeholder: $('#user-customer-clients').data('placeholder') });
    $('#user-projects').select2({ width:'100%', allowClear:true, placeholder: $('#user-projects').data('placeholder') });
  }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = window.jQuery;

    const allowCheckbox = document.getElementById('allow_send_tickets_by_mail');
    const section = document.getElementById('ticket-mail-settings-section');

    const userCustomersSelect = document.getElementById('user-customers');
    const customerClientSelect = document.getElementById('ticket_customer_client_id');
    const taskSelect = document.getElementById('ticket_task_id');

    if (!userCustomersSelect || !customerClientSelect || !taskSelect) {
        return;
    }

    const originalCustomerClientOptions = Array.from(customerClientSelect.options).map(option => ({
        value: String(option.value || ''),
        text: option.text,
        customerId: String(option.getAttribute('data-customer-id') || ''),
        selected: option.selected
    }));

    const originalTaskOptions = Array.from(taskSelect.options).map(option => ({
        value: String(option.value || ''),
        text: option.text,
        customerIds: String(option.getAttribute('data-customer-ids') || ''),
        projectId: String(option.getAttribute('data-project-id') || ''),
        selected: option.selected
    }));

    function getSelectedCustomerIds() {
        return Array.from(userCustomersSelect.options)
            .filter(option => option.selected)
            .map(option => String(option.value));
    }

    function toggleTicketMailSection() {
        if (!allowCheckbox || !section) return;

        const enabled = allowCheckbox.checked;

        section.querySelectorAll('.ticket-mail-field').forEach(function (el) {
            el.disabled = !enabled;
        });

        section.classList.toggle('opacity-50', !enabled);
    }

    function rebuildCustomerClientDropdown() {
        const selectedCustomerIds = getSelectedCustomerIds();
        const currentValue = String(customerClientSelect.value || '');

        customerClientSelect.innerHTML = '';

        originalCustomerClientOptions.forEach(function (opt) {
            if (!opt.value) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = opt.text;
                customerClientSelect.appendChild(option);
                return;
            }

            const shouldInclude = selectedCustomerIds.length > 0 && selectedCustomerIds.includes(opt.customerId);
            if (!shouldInclude) return;

            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            option.setAttribute('data-customer-id', opt.customerId);

            if (opt.value === currentValue) {
                option.selected = true;
            }

            customerClientSelect.appendChild(option);
        });

        if (customerClientSelect.options.length === 1) {
            customerClientSelect.value = '';
        }
    }

    function rebuildTaskDropdown() {
        const selectedCustomerIds = getSelectedCustomerIds();
        const currentValue = String(taskSelect.value || '');

        taskSelect.innerHTML = '';

        originalTaskOptions.forEach(function (opt) {
            if (!opt.value) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = opt.text;
                taskSelect.appendChild(option);
                return;
            }

            const optionCustomerIds = opt.customerIds
                ? opt.customerIds.split(',').map(v => String(v).trim()).filter(Boolean)
                : [];

            const shouldInclude =
                selectedCustomerIds.length > 0 &&
                optionCustomerIds.some(id => selectedCustomerIds.includes(id));

            if (!shouldInclude) return;

            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            option.setAttribute('data-customer-ids', opt.customerIds);
            option.setAttribute('data-project-id', opt.projectId);

            if (opt.value === currentValue) {
                option.selected = true;
            }

            taskSelect.appendChild(option);
        });

        if (taskSelect.options.length === 1) {
            taskSelect.value = '';
        }
    }

    function refreshTicketEmailDropdowns() {
        rebuildCustomerClientDropdown();
        rebuildTaskDropdown();
    }

    if (allowCheckbox && section) {
        allowCheckbox.addEventListener('change', toggleTicketMailSection);
        toggleTicketMailSection();
    }

    userCustomersSelect.addEventListener('change', refreshTicketEmailDropdowns);

    if ($ && $.fn && $.fn.select2) {
        $('#user-customers').on('change select2:select select2:unselect', function () {
            refreshTicketEmailDropdowns();
        });
    }

    refreshTicketEmailDropdowns();
});
</script>
@endpush

