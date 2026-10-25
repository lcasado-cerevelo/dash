@if(!empty($customer))
    {{Form::model($customer,array('route' => array('customer.update', $customer->id), 'method' => 'PUT', 'enctype' => 'multipart/form-data', 'class'=>'needs-validation', 'novalidate')) }}
@else
    {{ Form::open(['route' => ['customer.store'], 'method' => 'post', 'class'=>'needs-validation', 'novalidate']) }}
@endif

<div class="modal-body">
    <input type="hidden" name="user_id" value="{{ $user->id}}">

    <h6 class="sub-title">{{__('Basic Info')}}</h6>
    <div class="row">
        <div class="col-lg-4 col-md-4 col-sm-4">
            <div class="form-group">
                {{Form::label('name',__('Name'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('name',!empty($user->name) ? $user->name : null,array('class'=>'form-control','required'=>'required','placeholder' => __('Enter Customer Name')))}}
                </div>
            </div>
        </div>

        <x-mobile divClass="col-lg-4 col-md-4 col-sm-6" name="contact" label="{{ __('Contact') }}"
            placeholder="{{ __('Enter Contact') }}" id="contact" required>
        </x-mobile>

        <div class="col-lg-4 col-md-4 col-sm-4">
            <div class="form-group">
                {{Form::label('tax_number',__('Tax Number'),['class'=>'form-label'])}}
                <div class="form-icon-user">
                    {{Form::text('tax_number',null,array('class'=>'form-control','placeholder' => __('Enter Tax Number')))}}
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                <label class="form-label d-block">{{ __('Government Customer') }}</label>
                <div class="form-check form-switch">
                    {{ Form::hidden('is_goverment', 0) }}
                    {{ Form::checkbox('is_goverment', 1, old('is_goverment', !empty($customer) ? (bool)$customer->is_goverment : false), ['class' => 'form-check-input', 'id' => 'is_goverment']) }}
                    <label class="form-check-label" for="is_goverment">{{ __('Yes') }}</label>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{ Form::label('invoice_abrv', __('Invoice Abbreviation'), ['class' => 'form-label']) }}
                {{ Form::text('invoice_abrv', old('invoice_abrv', !empty($customer) ? $customer->invoice_abrv : null), ['class' => 'form-control', 'maxlength' => 4, 'placeholder' => __('e.g. SJ, PR, HUD')]) }}
                <small class="text-muted">{{ __('Max 4 characters') }}</small>
            </div>
        </div>

        <div class="col-lg-4 col-md-4 col-sm-6">
            <div class="form-group">
                {{ Form::label('invoice_att', __('Invoice Attention To'), ['class' => 'form-label']) }}
                {{ Form::text('invoice_att', old('invoice_att', !empty($customer) ? $customer->invoice_att : null), ['class' => 'form-control', 'maxlength' => 250, 'placeholder' => __('Enter person/department')]) }}
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('certification_text', __('Certification Text'), ['class' => 'form-label']) }}
                {{ Form::textarea('certification_text', old('certification_text', !empty($customer) ? $customer->certification_text : null), ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('Enter certification text to print on invoices (optional)')]) }}
            </div>
        </div>

        @stack('electronic_address')

        @if (module_is_active('CustomField') && !$customFields->isEmpty())
            <div class="col-md-12">
                <div class="tab-pane fade show" id="tab-2" role="tabpanel">
                    @include('custom-field::formBuilder',['fildedata' => !empty($customer->customField) ? $customer->customField : ''])
                </div>
            </div>
        @endif
    </div>

    <h6 class="sub-title">{{__('Billing Address')}}</h6>
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_name',__('Name'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('billing_name',null,array('class'=>'form-control','placeholder' => __('Enter Name'), 'required' => 'required'))}}
                </div>
            </div>
        </div>

        <x-mobile divClass="col-lg-6 col-md-6  col-sm-6" name="billing_phone" label="{{ __('Phone') }}"
            placeholder="{{ __('Enter Phone') }}" id="billing_phone" required>
        </x-mobile>

        <div class="col-md-12">
            <div class="form-group">
                {{Form::label('billing_address',__('Address'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="input-group">
                    {{Form::textarea('billing_address',null,array('class'=>'form-control','rows'=>3,'placeholder' => __('Enter Address'), 'required' => 'required'))}}
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_city',__('City'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('billing_city',null,array('class'=>'form-control','placeholder' => __('Enter City'), 'required' => 'required'))}}
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_state',__('State'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('billing_state',null,array('class'=>'form-control','placeholder' => __('Enter State'), 'required' => 'required'))}}
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_country',__('Country'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('billing_country',null,array('class'=>'form-control','placeholder' => __('Enter Country'), 'required' => 'required'))}}
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-6">
            <div class="form-group">
                {{Form::label('billing_zip',__('Zip Code'),array('class'=>'form-label')) }}<x-required></x-required>
                <div class="form-icon-user">
                    {{Form::text('billing_zip',null,array('class'=>'form-control','placeholder' => __('Enter Zip Code'), 'required' => 'required'))}}
                </div>
            </div>
        </div>
    </div>

    @if (company_setting('invoice_shipping_display') == 'on' || company_setting('proposal_shipping_display') == 'on' )
        <div class="col-md-12 text-end">
            <a href="#" id="billing_data" value="" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-placement="top"
               title="{{ __('Shipping Same As Billing') }}">
                <i class="ti ti-copy"></i>
            </a>
        </div>

        <h6 class="sub-title">{{__('Shipping Address')}}</h6>
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_name',__('Name'),array('class'=>'form-label')) }}
                    <div class="form-icon-user">
                        {{Form::text('shipping_name',null,array('class'=>'form-control','placeholder' => __('Enter Name')))}}
                    </div>
                </div>
            </div>

            <x-mobile divClass="col-lg-6 col-md-6 col-sm-6" name="shipping_phone" label="{{ __('Phone') }}"
                placeholder="{{ __('Enter Phone') }}" id="shipping_phone" required>
            </x-mobile>

            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('shipping_address',__('Address'),array('class'=>'form-label')) }}
                    <div class="input-group">
                        {{Form::textarea('shipping_address',null,array('class'=>'form-control','rows'=>3,'placeholder' => __('Enter Address')))}}
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_city',__('City'),array('class'=>'form-label')) }}
                    <div class="form-icon-user">
                        {{Form::text('shipping_city',null,array('class'=>'form-control','placeholder' => __('Enter City')))}}
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_state',__('State'),array('class'=>'form-label')) }}
                    <div class="form-icon-user">
                        {{Form::text('shipping_state',null,array('class'=>'form-control','placeholder' => __('Enter State')))}}
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_country',__('Country'),array('class'=>'form-label')) }}
                    <div class="form-icon-user">
                        {{Form::text('shipping_country',null,array('class'=>'form-control','placeholder' => __('Enter Country')))}}
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-sm-6">
                <div class="form-group">
                    {{Form::label('shipping_zip',__('Zip Code'),array('class'=>'form-label')) }}
                    <div class="form-icon-user">
                        {{Form::text('shipping_zip',null,array('class'=>'form-control','placeholder' => __('Enter Zip Code')))}}
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('account::customer._customer_clients', ['customer' => $customer ?? null])

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="sub-title mb-0">{{ __('Email Request Access') }}</h6>
        <button type="button" class="btn btn-primary btn-sm" id="add-map-row">{{ __('Add Row') }}</button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm" id="inbound-project-maps-table" style="min-width: 1700px;">
            <thead>
                <tr>
                    <th style="min-width: 150px;">{{ __('Mailbox') }}</th>
                    <th style="min-width: 120px;">{{ __('Match Type') }}</th>
                    <th style="min-width: 220px;">{{ __('Match Value') }}</th>
                    <th style="min-width: 220px;">{{ __('Label') }}</th>
                    <th style="min-width: 180px;">{{ __('Customer Client') }}</th>
                    <th style="min-width: 180px;">{{ __('Project') }}</th>
                    <th style="min-width: 160px;">{{ __('Category') }}</th>
                    <th style="min-width: 120px;">{{ __('Priority') }}</th>
                    <th style="width: 130px; min-width: 130px;">{{ __('Assign To') }}</th>
                    <th style="width: 80px; min-width: 80px; white-space: nowrap;">{{ __('Order') }}</th>
                    <th style="width: 70px; min-width: 70px; text-align: center;">{{ __('Active') }}</th>
                    <th style="width: 70px; min-width: 70px; text-align: center;">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $mapRows = old('inbound_project_maps', isset($inboundProjectMaps) ? $inboundProjectMaps->toArray() : []);
                @endphp

                @forelse($mapRows as $i => $map)
                    <tr>
                        <td>
                            <input type="hidden" name="inbound_project_maps[{{ $i }}][id]" value="{{ $map['id'] ?? '' }}">
                            <select name="inbound_project_maps[{{ $i }}][mailbox_id]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($mailboxes as $mailbox)
                                    <option value="{{ $mailbox->id }}" {{ (string)($map['mailbox_id'] ?? '') === (string)$mailbox->id ? 'selected' : '' }}>
                                        {{ $mailbox->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td>
                            <select name="inbound_project_maps[{{ $i }}][match_type]" class="form-control form-control-sm map-match-type">
                                <option value="email" {{ ($map['match_type'] ?? '') === 'email' ? 'selected' : '' }}>Email</option>
                                <option value="domain" {{ ($map['match_type'] ?? '') === 'domain' ? 'selected' : '' }}>Domain</option>
                                <option value="contains" {{ ($map['match_type'] ?? '') === 'contains' ? 'selected' : '' }}>Contains</option>
                            </select>
                        </td>

                        <td style="min-width: 220px;">
                            <input type="text" name="inbound_project_maps[{{ $i }}][match_value]" class="form-control form-control-sm" value="{{ $map['match_value'] ?? '' }}">
                        </td>

                        <td style="min-width: 220px;">
                            <input type="text" name="inbound_project_maps[{{ $i }}][match_name]" class="form-control form-control-sm" value="{{ $map['match_name'] ?? '' }}">
                        </td>

                        <td>
                            <select name="inbound_project_maps[{{ $i }}][customer_client_id]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($customerClients as $client)
                                    <option value="{{ $client->id }}" {{ (string)($map['customer_client_id'] ?? '') === (string)$client->id ? 'selected' : '' }}>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td>
                            <select name="inbound_project_maps[{{ $i }}][project_id]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ (string)($map['project_id'] ?? '') === (string)$project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td>
                            <select name="inbound_project_maps[{{ $i }}][category_id]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string)($map['category_id'] ?? '') === (string)$category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td>
                            <select name="inbound_project_maps[{{ $i }}][priority]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                <option value="1" {{ (string)($map['priority'] ?? '') === '1' ? 'selected' : '' }}>Very Low</option>
                                <option value="2" {{ (string)($map['priority'] ?? '') === '2' ? 'selected' : '' }}>Low</option>
                                <option value="3" {{ (string)($map['priority'] ?? '') === '3' ? 'selected' : '' }}>Regular</option>
                                <option value="4" {{ (string)($map['priority'] ?? '') === '4' ? 'selected' : '' }}>High</option>
                                <option value="5" {{ (string)($map['priority'] ?? '') === '5' ? 'selected' : '' }}>Very High</option>
                            </select>
                        </td>

                        <td style="width: 130px; min-width: 130px;">
                            @php
                                $selectedAssign = !empty($map['assign_to']) ? trim(explode(',', $map['assign_to'])[0]) : '';
                            @endphp
                            <select name="inbound_project_maps[{{ $i }}][assign_to]" class="form-control form-control-sm">
                                <option value="">{{ __('Select') }}</option>
                                @foreach($assignUsers as $u)
                                    <option value="{{ $u->id }}" {{ (string)$selectedAssign === (string)$u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td style="width: 80px; min-width: 80px;">
                            <input
                                type="number"
                                name="inbound_project_maps[{{ $i }}][sort_order]"
                                class="form-control form-control-sm map-sort-order text-center"
                                value="{{ ($map['match_type'] ?? '') === 'domain' ? 9999 : ($map['sort_order'] ?? 100) }}"
                                {{ ($map['match_type'] ?? '') === 'domain' ? 'readonly' : '' }}
                            >
                        </td>

                        <td class="text-center">
                            <input type="checkbox" name="inbound_project_maps[{{ $i }}][is_active]" value="1" {{ !empty($map['is_active']) ? 'checked' : '' }}>
                        </td>

                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-map-row">
                                <i class="ti ti-x"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{ Form::submit(__('Update'), ['class' => 'btn btn-primary']) }}
</div>

{{Form::close()}}

<script>
(function () {
    const table = document.getElementById('inbound-project-maps-table');
    const addBtn = document.getElementById('add-map-row');

    if (!table || !addBtn) return;

    let mapIndex = table.querySelectorAll('tbody tr').length;

    function syncSortOrderByMatchType(row) {
        const matchType = row.querySelector('.map-match-type')?.value || '';
        const sortOrder = row.querySelector('.map-sort-order');

        if (!sortOrder) return;

        if (matchType === 'domain') {
            sortOrder.value = 9999;
            sortOrder.readOnly = true;
        } else {
            if (sortOrder.value === '9999' || sortOrder.value === '') {
                sortOrder.value = 100;
            }
            sortOrder.readOnly = false;
        }
    }

    addBtn.addEventListener('click', function () {
        const tbody = table.querySelector('tbody');

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="hidden" name="inbound_project_maps[${mapIndex}][id]" value="">
                <select name="inbound_project_maps[${mapIndex}][mailbox_id]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    @foreach($mailboxes as $mailbox)
                        <option value="{{ $mailbox->id }}">{{ $mailbox->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="inbound_project_maps[${mapIndex}][match_type]" class="form-control form-control-sm map-match-type">
                    <option value="email">Email</option>
                    <option value="domain">Domain</option>
                    <option value="contains">Contains</option>
                </select>
            </td>
            <td style="min-width: 220px;">
                <input type="text" name="inbound_project_maps[${mapIndex}][match_value]" class="form-control form-control-sm">
            </td>
            <td style="min-width: 220px;">
                <input type="text" name="inbound_project_maps[${mapIndex}][match_name]" class="form-control form-control-sm">
            </td>
            <td>
                <select name="inbound_project_maps[${mapIndex}][customer_client_id]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    @foreach($customerClients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="inbound_project_maps[${mapIndex}][project_id]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="inbound_project_maps[${mapIndex}][category_id]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="inbound_project_maps[${mapIndex}][priority]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    <option value="1">Very Low</option>
                    <option value="2">Low</option>
                    <option value="3">Regular</option>
                    <option value="4">High</option>
                    <option value="5">Very High</option>
                </select>
            </td>
            <td style="width: 130px; min-width: 130px;">
                <select name="inbound_project_maps[${mapIndex}][assign_to]" class="form-control form-control-sm">
                    <option value="">{{ __('Select') }}</option>
                    @foreach($assignUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </td>
            <td style="width: 80px; min-width: 80px;">
                <input type="number" name="inbound_project_maps[${mapIndex}][sort_order]" class="form-control form-control-sm map-sort-order text-center" value="100">
            </td>
            <td class="text-center">
                <input type="checkbox" name="inbound_project_maps[${mapIndex}][is_active]" value="1" checked>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-map-row">
                    <i class="ti ti-x"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);
        syncSortOrderByMatchType(row);
        mapIndex++;
    });

    table.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-map-row');
        if (btn) {
            e.preventDefault();
            btn.closest('tr')?.remove();
        }
    });

    table.addEventListener('change', function (e) {
        if (e.target.classList.contains('map-match-type')) {
            syncSortOrderByMatchType(e.target.closest('tr'));
        }
    });

    table.querySelectorAll('tbody tr').forEach(function (row) {
        syncSortOrderByMatchType(row);
    });
})();
</script>