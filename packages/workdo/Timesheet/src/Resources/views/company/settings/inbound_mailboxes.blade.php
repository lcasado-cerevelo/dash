{{--
    packages/workdo/Timesheet/src/Resources/views/company/settings/inbound_mailboxes.blade.php

    Este archivo es un PARTIAL — no tiene @extends ni @section.
    Se inyecta como un bloque HTML dentro de la página global /company/settings
    mediante CompanySettingListener → CompanySettingEvent, igual que hace Taskly.
--}}

@php
    // Datos ya vienen del SettingsController, pero si por algún motivo
    // se llama directo al view sin datos, los cargamos como fallback.
    if (!isset($mailboxes)) {
        $mailboxes = \Workdo\Timesheet\Entities\TimesheetInboundMailbox::orderBy('name')->get();
    }
    if (!isset($senderMaps)) {
        $senderMaps = \Workdo\Timesheet\Entities\TimesheetInboundSenderMap::with(['mailbox','employee'])
            ->orderBy('sort_order')->get();
    }
    if (!isset($processedLog)) {
        $processedLog = \Illuminate\Support\Facades\DB::table('timesheet_inbound_processed')
            ->orderByDesc('id')->limit(50)->get();
    }
    $employees       = $employees       ?? collect();
    $projects        = $projects        ?? collect();
    $tasks           = $tasks           ?? collect();
    $customers       = $customers       ?? collect();
    $customerClients = $customerClients ?? collect();
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECCIÓN A: Buzones IMAP                                                 --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="card mt-4">
    <div class="card-header">
        <h5>{{ __('Timesheet Inbound Mailboxes') }}</h5>
        <small class="text-muted">
            {{ __('Manage IMAP mailboxes used to create timesheets from inbound emails.') }}
        </small>
    </div>
    <div class="card-body">

        {{-- Formulario: crear mailbox --}}
        <form method="POST" action="{{ route('timesheet.email.mailbox.store') }}" class="mb-4">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm"
                           value="{{ old('name') }}" required placeholder="{{ __('e.g. Timesheets HR') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('IMAP Host') }} <span class="text-danger">*</span></label>
                    <input type="text" name="imap_host" class="form-control form-control-sm"
                           value="{{ old('imap_host') }}" required placeholder="imap.company.com">
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('Port') }}</label>
                    <input type="number" name="imap_port" class="form-control form-control-sm"
                           value="{{ old('imap_port', 993) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Username') }} <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control form-control-sm"
                           value="{{ old('username') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Password') }} <span class="text-danger">*</span></label>
                    <input type="password" name="password_plain" class="form-control form-control-sm"
                           autocomplete="new-password" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('Folder') }}</label>
                    <input type="text" name="folder" class="form-control form-control-sm"
                           value="{{ old('folder', 'INBOX') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label">{{ __('Processed Folder') }}</label>
                    <input type="text" name="processed_folder" class="form-control form-control-sm"
                           value="{{ old('processed_folder') }}" placeholder="Processed">
                </div>
                <div class="col-md-1">
                    <div class="form-check mt-1">
                        <input type="checkbox" name="imap_ssl" class="form-check-input" id="new_mb_ssl" checked>
                        <label class="form-check-label" for="new_mb_ssl">SSL</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="new_mb_active" checked>
                        <label class="form-check-label" for="new_mb_active">{{ __('Active') }}</label>
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i>{{ __('Add Mailbox') }}
                </button>
            </div>
        </form>

        {{-- Tabla de mailboxes existentes --}}
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Host') }}</th>
                        <th>{{ __('Port') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Folder') }}</th>
                        <th>{{ __('Processed Folder') }}</th>
                        <th class="text-center">SSL</th>
                        <th class="text-center">{{ __('Active') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mailboxes as $mailbox)
                        <tr>
                            <td>{{ $mailbox->name }}</td>
                            <td>{{ $mailbox->imap_host }}</td>
                            <td>{{ $mailbox->imap_port }}</td>
                            <td>{{ $mailbox->username }}</td>
                            <td>{{ $mailbox->folder }}</td>
                            <td>{{ $mailbox->processed_folder ?? '—' }}</td>
                            <td class="text-center">
                                {{ $mailbox->imap_ssl ? __('Yes') : __('No') }}
                            </td>
                            <td class="text-center">
                                {{ $mailbox->is_active ? __('Yes') : __('No') }}
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#edit-ts-mailbox-{{ $mailbox->id }}">
                                    {{ __('Edit') }}
                                </button>
                                <form method="POST"
                                      action="{{ route('timesheet.email.mailbox.destroy', $mailbox->id) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete this mailbox and all its sender maps?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {{-- Fila de edición colapsable --}}
                        <tr class="collapse" id="edit-ts-mailbox-{{ $mailbox->id }}">
                            <td colspan="9">
                                <form method="POST"
                                      action="{{ route('timesheet.email.mailbox.update', $mailbox->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Name') }}</label>
                                            <input type="text" name="name" class="form-control form-control-sm"
                                                   value="{{ $mailbox->name }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('IMAP Host') }}</label>
                                            <input type="text" name="imap_host" class="form-control form-control-sm"
                                                   value="{{ $mailbox->imap_host }}" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Port') }}</label>
                                            <input type="number" name="imap_port" class="form-control form-control-sm"
                                                   value="{{ $mailbox->imap_port }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Username') }}</label>
                                            <input type="text" name="username" class="form-control form-control-sm"
                                                   value="{{ $mailbox->username }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">
                                                {{ __('New Password') }}
                                                <small class="text-muted">({{ __('leave blank to keep') }})</small>
                                            </label>
                                            <input type="password" name="password_plain"
                                                   class="form-control form-control-sm"
                                                   autocomplete="new-password">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Folder') }}</label>
                                            <input type="text" name="folder" class="form-control form-control-sm"
                                                   value="{{ $mailbox->folder }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Processed Folder') }}</label>
                                            <input type="text" name="processed_folder"
                                                   class="form-control form-control-sm"
                                                   value="{{ $mailbox->processed_folder }}">
                                        </div>
                                        <div class="col-md-1">
                                            <div class="form-check mt-1">
                                                <input type="checkbox" name="imap_ssl"
                                                       class="form-check-input"
                                                       id="edit_ssl_{{ $mailbox->id }}"
                                                       {{ $mailbox->imap_ssl ? 'checked' : '' }}>
                                                <label class="form-check-label" for="edit_ssl_{{ $mailbox->id }}">SSL</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active"
                                                       class="form-check-input"
                                                       id="edit_active_{{ $mailbox->id }}"
                                                       {{ $mailbox->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="edit_active_{{ $mailbox->id }}">{{ __('Active') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ __('Save Changes') }}
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                {{ __('No mailboxes configured yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECCIÓN B: Mapas de remitentes                                          --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="card mt-4">
    <div class="card-header">
        <h5>{{ __('Timesheet Sender Maps') }}</h5>
        <small class="text-muted">
            {{ __('Map a sender email or domain to an employee and default project/task/customer. The email body becomes the timesheet description; the subject must contain the time range (e.g. "2026-04-07 09:00 to 2026-04-07 17:00").') }}
        </small>
    </div>
    <div class="card-body">

        {{-- Formulario: crear sender map --}}
        <form method="POST" action="{{ route('timesheet.email.sendermap.store') }}" class="mb-4">
            @csrf
            <div class="row g-3 align-items-end">
                {{-- Tipo y valor --}}
                <div class="col-md-1">
                    <label class="form-label">{{ __('Match Type') }} <span class="text-danger">*</span></label>
                    <select name="match_type" class="form-select form-select-sm" required>
                        <option value="email">{{ __('Email') }}</option>
                        <option value="domain">{{ __('Domain') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('Value') }} <span class="text-danger">*</span></label>
                    <input type="text" name="match_value" class="form-control form-control-sm"
                           value="{{ old('match_value') }}" required
                           placeholder="john@acme.com  or  @acme.com">
                </div>

                {{-- Mailbox (opcional) --}}
                <div class="col-md-2">
                    <label class="form-label">{{ __('Mailbox') }}</label>
                    <select name="mailbox_id" class="form-select form-select-sm">
                        <option value="">{{ __('All mailboxes') }}</option>
                        @foreach($mailboxes as $mb)
                            <option value="{{ $mb->id }}">{{ $mb->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Empleado --}}
                <div class="col-md-2">
                    <label class="form-label">{{ __('Employee') }}</label>
                    <select name="employee_user_id" class="form-select form-select-sm">
                        <option value="">{{ __('Resolve from From address') }}</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Proyecto --}}
                @if($projects->isNotEmpty())
                <div class="col-md-1">
                    <label class="form-label">{{ __('Project') }}</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($projects as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Tarea --}}
                @if($tasks->isNotEmpty())
                <div class="col-md-1">
                    <label class="form-label">{{ __('Task') }}</label>
                    <select name="task_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($tasks as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Customer --}}
                @if($customers->isNotEmpty())
                <div class="col-md-1">
                    <label class="form-label">{{ __('Customer') }}</label>
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($customers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Customer Client --}}
                @if($customerClients->isNotEmpty())
                <div class="col-md-1">
                    <label class="form-label">{{ __('Client') }}</label>
                    <select name="customer_client_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($customerClients as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Orden + Activo --}}
                <div class="col-md-1">
                    <label class="form-label">{{ __('Order') }}</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" value="100">
                    <div class="form-check mt-1">
                        <input type="checkbox" name="is_active" class="form-check-input"
                               id="new_sm_active" checked>
                        <label class="form-check-label" for="new_sm_active">{{ __('Active') }}</label>
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i>{{ __('Add Sender Map') }}
                </button>
            </div>
        </form>

        {{-- Tabla de sender maps existentes --}}
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Value') }}</th>
                        <th>{{ __('Mailbox') }}</th>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Project') }}</th>
                        <th>{{ __('Task') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Client') }}</th>
                        <th class="text-center">{{ __('Order') }}</th>
                        <th class="text-center">{{ __('Active') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($senderMaps as $sm)
                        <tr>
                            <td><span class="badge bg-info">{{ $sm->match_type }}</span></td>
                            <td><code>{{ $sm->match_value }}</code></td>
                            <td>{{ optional($sm->mailbox)->name ?? __('All') }}</td>
                            <td>
                                @if($sm->employee)
                                    {{ $sm->employee->name }}<br>
                                    <small class="text-muted">{{ $sm->employee->email }}</small>
                                @else
                                    <span class="text-muted small">{{ __('From address') }}</span>
                                @endif
                            </td>
                            <td>{{ $sm->project_id ? ($projects[$sm->project_id] ?? "#$sm->project_id") : '—' }}</td>
                            <td>{{ $sm->task_id    ? ($tasks[$sm->task_id]       ?? "#$sm->task_id")    : '—' }}</td>
                            <td>{{ $sm->customer_id ? ($customers[$sm->customer_id] ?? "#$sm->customer_id") : '—' }}</td>
                            <td>{{ $sm->customer_client_id ? ($customerClients[$sm->customer_client_id] ?? "#$sm->customer_client_id") : '—' }}</td>
                            <td class="text-center">{{ $sm->sort_order }}</td>
                            <td class="text-center">{{ $sm->is_active ? __('Yes') : __('No') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#edit-ts-sm-{{ $sm->id }}">
                                    {{ __('Edit') }}
                                </button>
                                <form method="POST"
                                      action="{{ route('timesheet.email.sendermap.destroy', $sm->id) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete this sender map?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {{-- Fila de edición colapsable --}}
                        <tr class="collapse" id="edit-ts-sm-{{ $sm->id }}">
                            <td colspan="11">
                                <form method="POST"
                                      action="{{ route('timesheet.email.sendermap.update', $sm->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Type') }}</label>
                                            <select name="match_type" class="form-select form-select-sm">
                                                <option value="email"  {{ $sm->match_type === 'email'  ? 'selected' : '' }}>{{ __('Email') }}</option>
                                                <option value="domain" {{ $sm->match_type === 'domain' ? 'selected' : '' }}>{{ __('Domain') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Value') }}</label>
                                            <input type="text" name="match_value"
                                                   class="form-control form-control-sm"
                                                   value="{{ $sm->match_value }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Mailbox') }}</label>
                                            <select name="mailbox_id" class="form-select form-select-sm">
                                                <option value="">{{ __('All') }}</option>
                                                @foreach($mailboxes as $mb)
                                                    <option value="{{ $mb->id }}"
                                                            {{ $sm->mailbox_id == $mb->id ? 'selected' : '' }}>
                                                        {{ $mb->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">{{ __('Employee') }}</label>
                                            <select name="employee_user_id" class="form-select form-select-sm">
                                                <option value="">{{ __('From address') }}</option>
                                                @foreach($employees as $emp)
                                                    <option value="{{ $emp->id }}"
                                                            {{ $sm->employee_user_id == $emp->id ? 'selected' : '' }}>
                                                        {{ $emp->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if($projects->isNotEmpty())
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Project') }}</label>
                                            <select name="project_id" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($projects as $id => $name)
                                                    <option value="{{ $id }}" {{ $sm->project_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        @if($tasks->isNotEmpty())
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Task') }}</label>
                                            <select name="task_id" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($tasks as $id => $name)
                                                    <option value="{{ $id }}" {{ $sm->task_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        @if($customers->isNotEmpty())
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Customer') }}</label>
                                            <select name="customer_id" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($customers as $id => $name)
                                                    <option value="{{ $id }}" {{ $sm->customer_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        @if($customerClients->isNotEmpty())
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Client') }}</label>
                                            <select name="customer_client_id" class="form-select form-select-sm">
                                                <option value="">—</option>
                                                @foreach($customerClients as $id => $name)
                                                    <option value="{{ $id }}" {{ $sm->customer_client_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        <div class="col-md-1">
                                            <label class="form-label">{{ __('Order') }}</label>
                                            <input type="number" name="sort_order"
                                                   class="form-control form-control-sm"
                                                   value="{{ $sm->sort_order }}">
                                            <div class="form-check mt-1">
                                                <input type="checkbox" name="is_active"
                                                       class="form-check-input"
                                                       id="edit_sm_active_{{ $sm->id }}"
                                                       {{ $sm->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="edit_sm_active_{{ $sm->id }}">
                                                    {{ __('Active') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ __('Save Changes') }}
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">
                                {{ __('No sender maps configured yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- SECCIÓN C: Log de los últimos correos procesados                        --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="card mt-4">
    <div class="card-header">
        <h5>{{ __('Timesheet Email Processing Log') }}</h5>
        <small class="text-muted">{{ __('Last 50 processed emails.') }}</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Processed At') }}</th>
                        <th>{{ __('Mailbox ID') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('Message-ID') }}</th>
                        <th class="text-center">{{ __('Timesheets') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($processedLog as $log)
                        @php
                            $badgeClass = match($log->status) {
                                'ok'                   => 'bg-success',
                                'skipped_no_map'       => 'bg-secondary',
                                'skipped_no_employee'  => 'bg-warning text-dark',
                                'skipped_parse_error'  => 'bg-danger',
                                default                => 'bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td><small>{{ $log->processed_at }}</small></td>
                            <td><small>{{ $log->mailbox_id }}</small></td>
                            <td><small>{{ $log->from_email }}</small></td>
                            <td>
                                <small class="d-inline-block text-truncate" style="max-width: 220px"
                                       title="{{ $log->message_id }}">
                                    {{ $log->message_id }}
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary">{{ $log->timesheets_created }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }}">{{ $log->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                {{ __('No emails processed yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
