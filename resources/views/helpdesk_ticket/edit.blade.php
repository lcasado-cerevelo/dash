@extends('layouts.main')

@section('page-title')
    {{ __('Reply Ticket') }} - {{ $ticket->ticket_id }}
@endsection

@section('page-breadcrumb')
    <a href="{{ route('helpdesk.index') }}">{{ __('Tickets') }}</a>,{{ __('Reply') }}
@endsection

@section('page-action')
<div>
    @permission('helpdesk ticket edit')
        @if(
            (($ticket->status === 'New') &&
            (
                Auth::user()->id == $ticket->created_by ||
                Auth::user()->id == $ticket->user_id ||
                Auth::user()->type == 'super admin' ||
                Auth::user()->type == 'company'
            ))
            ||
            (
                Auth::user()->type == 'super admin' ||
                Auth::user()->type == 'company'
            )
        )
            <div class="btn btn-sm btn-info btn-icon m-1 float-end">
                <a href="#ticket-info" class="" type="button" data-bs-toggle="collapse" data-bs-toggle="tooltip"
                    data-bs-placement="top" title="{{ __('Edit Ticket') }}"><i class="ti ti-pencil text-white"></i></a>
            </div>
        @endif
    @endpermission
</div>
@endsection

@push('css')
    <link href="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
    @php
        $authUser = Auth::user();

        $isSuper = \Laratrust::hasRole('super admin')
            || \Laratrust::hasRole('superadministrator')
            || (isset($authUser->type) && strtolower($authUser->type) === 'super admin');

        $isCompany = \Laratrust::hasRole('company')
            || \Laratrust::hasRole('Company')
            || (isset($authUser->type) && strtolower($authUser->type) === 'company');

        $isPrivileged = $isSuper || $isCompany;
        $isNewTicket = strtolower(trim((string) $ticket->status)) === 'new';

        $showAdminFields = !$isNewTicket || $isPrivileged;
        $showDescAndAttachments = !$isNewTicket || !$isPrivileged;
        $showCompanyNewAssignmentFields = $isCompany && $isNewTicket;
    @endphp

    @permission('helpdesk ticket edit')
        {{ Form::model($ticket, [
            'route' => ['helpdesk.update', $ticket->id],
            'id' => 'ticket-info',
            'class' => 'collapse mt-3 needs-validation',
            'method' => 'PUT',
            'enctype' => 'multipart/form-data',
            'novalidate'
        ]) }}

        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body">

                        @if($showAdminFields && Auth::user()->type == 'super admin')
                            <div class="row">
                                <div class="form-group col-md-6" id="customname">
                                    @php
                                        $name = 'Customers';
                                    @endphp
                                    <label class="require form-label">{{ $name }}</label><x-required></x-required>
                                    <select class="form-control select_person_email" name="name" {{ !empty($errors->first('name')) ? 'is-invalid' : '' }} required>
                                        <option value="">{{ __('— Select User —') }}</option>
                                        @foreach ($users as $key => $value)
                                            <option value="{{ $key }}" {{ $ticket->user_id == $key ? 'selected' : '' }}>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback">
                                        {{ $errors->first('name') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="require form-label">{{ __('Email') }}</label><x-required></x-required>
                                    <input class="form-control emailAddressField {{ !empty($errors->first('email')) ? 'is-invalid' : '' }}"
                                        type="email" name="email" id="emailAddressField" required value="{{ $ticket->email }}"
                                        placeholder="{{ __('Email') }}" readonly style="background-color:#e9ecef">
                                    @if ($errors->has('email'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('email') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            @if($showCompanyNewAssignmentFields)
                                <div class="form-group col-md-6">
                                    <label class="form-label">{{ __('Requested By') }}</label>
                                    <select name="created_by_user_id"
                                            id="assigned_user_id_company_new"
                                            class="form-control">
                                        <option value="">{{ __('— Select User —') }}</option>
                                        @foreach($assignableUsers as $u)
                                            <option value="{{ $u->id }}"
                                                    data-email="{{ $u->email }}"
                                                    {{ (string) old('assigned_user_id', $ticket->created_by) === (string) $u->id ? 'selected' : '' }}>
                                                {{ $u->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="form-label">{{ __('Email') }}</label>
                                    <input type="email"
                                           name="email"
                                           id="company_new_ticket_email"
                                           class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                           value="{{ old('email', $ticket->email) }}"
                                           placeholder="{{ __('Email') }}">
                                    @if ($errors->has('email'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('email') }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="form-group col-md-6">
                                <label class="require form-label">{{ __('Subject') }}</label><x-required></x-required>
                                <input class="form-control {{ !empty($errors->first('subject')) ? 'is-invalid' : '' }}"
                                    type="text" name="subject" required value="{{ old('subject', $ticket->subject) }}"
                                    placeholder="{{ __('Subject') }}">
                                @if ($errors->has('subject'))
                                    <div class="invalid-feedback">
                                        {{ $errors->first('subject') }}
                                    </div>
                                @endif
                            </div>

                            @if($showAdminFields)
                                <div class="form-group col-md-6">
                                    <label class="require form-label">{{ __('Category') }}</label><x-required></x-required>
                                    <select class="form-select {{ !empty($errors->first('category')) ? 'is-invalid' : '' }}"
                                        name="category" required>
                                        <option value="">{{ __('— Select Category —') }}</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @if ((string)old('category', $ticket->category) === (string)$category->id) selected @endif>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('category'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('category') }}
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="require form-label">{{ __('Status') }}</label><x-required></x-required>
                                    <select class="form-select {{ !empty($errors->first('status')) ? 'is-invalid' : '' }}"
                                        name="status" required>
                                        @foreach(['New','In Progress','On Hold','Validation','Closed','Canceled'] as $statusOption)
                                            <option value="{{ $statusOption }}"
                                                @if (old('status', $ticket->status) === $statusOption) selected @endif>
                                                {{ $statusOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('status'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('status') }}
                                        </div>
                                    @endif
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
                                            <option value="{{ $value }}"
                                                @if ((string)old('priority', $ticket->priority) === (string)$value) selected @endif>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @php
                                    $selectedTaskId     = old('task_id', $ticket->task_id ?? '');
                                    $selectedCustomerId = old('customer_id', $ticket->customer_id ?? '');
                                    $selectedClientId   = old('customer_client_id', $ticket->customer_client_id ?? '');
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
                                                    {{ (string)(old('assigned_user_id', $selectedAssigneeId ?? '')) === (string)$u->id ? 'selected' : '' }}>
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
                                           class="btn btn-outline-info w-100 {{ old('parent_ticket_id', $ticket->parent_ticket_id) ? '' : 'disabled' }}"
                                           data-bs-toggle="modal"
                                           data-bs-target="#parentTicketPreviewModal"
                                           aria-disabled="{{ old('parent_ticket_id', $ticket->parent_ticket_id) ? 'false' : 'true' }}">
                                            {{ __('View Ticket') }}
                                        </a>
                                    </div>                                    
                                    <div class="form-group col-md-12">
                                        <label class="form-label">{{ __('Can View Ticket') }}</label>
                                        <select name="visible_to_user_ids[]" id="visible_to_user_ids" class="form-control" multiple>
                                            @foreach($assignableUsers as $au)
                                                <option value="{{ $au->id }}" @selected(in_array($au->id, old('visible_to_user_ids', $ticketVisibleIds ?? [])))>
                                                    {{ $au->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            @endif

                            @if($showDescAndAttachments)
                                <div class="form-group col-md-12">
                                    <label class="require form-label">{{ __('Attachments') }}
                                        <small>({{ __('You can select multiple files') }})</small>
                                    </label>
                                    <div class="choose-file form-group">
                                        <label for="file" class="form-label d-block">
                                            <input type="file" name="attachments[]" id="file"
                                                class="form-control mb-2 {{ $errors->has('attachments') ? ' is-invalid' : '' }}"
                                                multiple data-filename="multiple_file_selection"
                                                onchange="document.getElementById('blah2') && (document.getElementById('blah2').src = window.URL.createObjectURL(this.files[0]))">

                                            <div class="invalid-feedback">
                                                {{ $errors->first('attachments') }}
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group col-md-12">
                                    <div class="mx-3">
                                        <p class="multiple_file_selection mb-0"></p>
                                        <div class="w-100 attachment_list row">
                                            @if (!empty($ticket->attachments))
                                                @php $attachments = json_decode($ticket->attachments); @endphp
                                                @foreach ($attachments as $index => $attachment)
                                                    <div class="col-auto px-0 mt-2">
                                                        <a download href="{{ get_file($attachment->path) }}"
                                                            class="btn btn-sm btn-primary d-inline-flex align-items-center"
                                                            data-bs-toggle="tooltip" title="{{ __('Download') }}">
                                                            <i class="ti ti-arrow-bar-to-down me-2"></i>{{ $attachment->name }}
                                                        </a>

                                                        @if(!empty($canDeleteTicketAttachment))
                                                            <a href="javascript:void(0);"
                                                                class="bg-danger ms-2 mx-3 btn btn-sm d-inline-flex align-items-center"
                                                                title="{{ __('Delete') }}"
                                                                onclick="if(confirm('{{ __('Are You Sure?') }}')) document.getElementById('attachment-delete-form-{{ $index }}').submit();">
                                                                <i class="ti ti-trash text-white"></i>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-md-12 mt-2">
                                    <label class="require form-label">{{ __('Description') }}</label>
                                    <textarea name="description"
                                        class="form-control summernote {{ !empty($errors->first('description')) ? 'is-invalid' : '' }}"
                                        id="description">{!! old('description', $ticket->description) !!}</textarea>
                                    @if ($errors->has('description'))
                                        <div class="invalid-feedback">
                                            {{ $errors->first('description') }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="text-end">
                            <a class="btn btn-secondary me-1" href="{{ route('helpdesk.index') }}">{{ __('Cancel') }}</a>
                            <button class="btn btn-primary btn-block btn-submit" type="submit">{{ __('Update') }}</button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        {{ Form::close() }}

        @if (!empty($ticket->attachments))
            @php
                $attachments = json_decode($ticket->attachments);
            @endphp

            @if (!empty($attachments) && count($attachments))
                @foreach ($attachments as $index => $attachment)
                    <form method="POST"
                        id="attachment-delete-form-{{ $index }}"
                        action="{{ route('helpdesk-ticket.attachment.destroy', [$ticket->id, $index]) }}"
                        style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        @endif

    @endpermission

    <div class="row mt-3">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">

            <div class="card">
                <div class="card-header pt-3 pb-3">
                    @php
                        $priorityLabels = [1 => 'Very Low', 2 => 'Low', 3 => 'Regular', 4 => 'High', 5 => 'Very High'];
                        $statusBadgeClass = match ($ticket->status) {
                            'In Progress' => 'bg-warning text-dark',
                            'On Hold' => 'bg-danger',
                            'Validation' => 'bg-info',
                            'Closed' => 'bg-secondary',
                            'Canceled' => 'bg-dark',
                            default => 'bg-success',
                        };
                    @endphp

                    <div class="row w-100 align-items-start g-3 m-0">
                        <div class="col-12 col-md-4 text-start">
                            <h5 class="mb-1">{{ $ticket->name }}</h5>
                            <div>{{ $ticket->email }}</div>
                            <div class="text-muted small">{{ $ticket->created_at->diffForHumans() }}</div>
                        </div>

                        <div class="col-12 col-md-4 text-center">
                            <div>
                                <strong>{{ __('Customer / Client') }}:</strong>
                                {{ optional($ticket->customer)->name ?? '---' }}
                                /
                                {{ optional($ticket->customerClient)->name ?? '---' }}
                            </div>

                            @php
                                $taskTitle = optional($ticket->task)->title;
                                $projectName = data_get($ticket, 'task.project.name');
                            @endphp

                            @if($taskTitle || $projectName)
                                <div>
                                    <strong>{{ __('Task / Project') }}:</strong>
                                    {{ $taskTitle ?? '---' }}{{ ($taskTitle && $projectName) ? ' / ' : '' }}{{ $projectName ?? '' }}
                                </div>
                            @endif

                            <div><strong>{{ __('Subject') }}:</strong> {{ $ticket->subject }}</div>

                            <div>
                                <strong>{{ __('Assigned') }}:</strong> {{ optional($ticket->user)->name ?? '---' }}
                                @if($canHeaderEdit && $ticket->status !== 'Closed')
                                    <a href="#reassignModal" data-bs-toggle="modal" class="ms-2 text-info">
                                        <i class="ti ti-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 col-md-4 text-md-end text-start">
                            <div class="mb-2 d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                <strong>{{ __('Status') }}:</strong>

                                @if($canHeaderEdit)
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm {{ $statusBadgeClass }} dropdown-toggle" data-bs-toggle="dropdown" type="button">
                                            {{ __($ticket->status) }}
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-2">
                                            <form method="POST" action="{{ route('helpdesk.change-status', $ticket->id) }}">
                                                @csrf
                                                <select name="status" class="form-control mb-2">
                                                    @foreach(['In Progress','On Hold','Validation','Closed','Canceled'] as $status)
                                                        <option value="{{ $status }}" @selected($ticket->status === $status)>{{ $status }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-primary btn-sm w-100">{{ __('Save') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge {{ $statusBadgeClass }}">{{ __($ticket->status) }}</span>
                                @endif
                            </div>

                            <div class="mb-2 d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                <strong>{{ __('Category') }}:</strong>

                                @if($canPriorityCategoryEdit)
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-info dropdown-toggle" data-bs-toggle="dropdown" type="button">
                                            {{ optional($ticket->tcategory)->name ?? '---' }}
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-2">
                                            <form method="POST" action="{{ route('helpdesk.change-category', $ticket->id) }}">
                                                @csrf
                                                <select name="category" class="form-control mb-2">
                                                    @foreach($categories as $category)
                                                        <option value="{{ $category->id }}" @selected((int)$ticket->category === (int)$category->id)>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-primary btn-sm w-100">{{ __('Save') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge bg-info">{{ optional($ticket->tcategory)->name ?? '---' }}</span>
                                @endif
                            </div>

                            <div class="d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                <strong>{{ __('Priority') }}:</strong>

                                @if($canPriorityCategoryEdit)
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                                            {{ $priorityLabels[(int)$ticket->priority] ?? 'Regular' }}
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end p-2">
                                            <form method="POST" action="{{ route('helpdesk.change-priority', $ticket->id) }}">
                                                @csrf
                                                <select name="priority" class="form-control mb-2">
                                                    @foreach($priorityLabels as $k => $label)
                                                        <option value="{{ $k }}" @selected((int)$ticket->priority === (int)$k)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-primary btn-sm w-100">{{ __('Save') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge bg-primary">{{ $priorityLabels[(int)$ticket->priority] ?? 'Regular' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body pb-0 pt-3">
                    <div>
                        @php
                            use Illuminate\Support\Str;
                            $hasDesc = Str::of($ticket->description)->stripTags()->trim()->isNotEmpty();
                        @endphp

                        @if($hasDesc)
                            <div>{!! $ticket->description !!}</div>
                        @endif
                    </div>

                    @if (!empty($ticket->attachments))
                        @php $attachments = json_decode($ticket->attachments); @endphp
                        @if (count($attachments))
                            <div class="m-1">
                                <p></p><h6>{{ __('Attachments') }} :</h6></p>
                                <ul class="list-group list-group-flush">
                                    @foreach ($attachments as $index => $attachment)
                                        <li class="list-group-item px-0">
                                            {{ $attachment->name }}

                                            <a href="javascript:void(0);"
                                               onclick="openAttachment('{{ get_file($attachment->path) }}')"
                                               class="edit-icon py-1 ml-2"
                                               title="Preview">
                                               <i class="fas fa-eye ms-2"></i>
                                            </a>

                                            <a href="{{ get_file($attachment->path) }}"
                                               class="edit-icon py-1 ml-2"
                                               title="{{ __('Download') }}"
                                               download>
                                                <i class="fas fa-download ms-2"></i>
                                            </a>

                                            @if(!empty($canDeleteTicketAttachment))
                                                <a href="javascript:void(0);"
                                                   class="edit-icon py-1 ml-2 text-danger"
                                                   title="{{ __('Delete') }}"
                                                   onclick="if(confirm('{{ __('Are You Sure?') }}')) document.getElementById('attachment-delete-form-{{ $index }}').submit();">
                                                    <i class="fas fa-trash ms-2"></i>
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                    @if($ticket->parentTicket)
                        <div class="mb-3">
                            <strong>{{ __('Parent Ticket') }}:</strong>
                            <a href="{{ route('helpdesk.view', [\Illuminate\Support\Facades\Crypt::encrypt($ticket->parentTicket->ticket_id)]) }}">
                                #{{ $ticket->parentTicket->ticket_id }} - {{ $ticket->parentTicket->subject }}
                            </a>
                        </div>
                    @endif                    
                </div>
            </div>

            @foreach ($ticket->conversions as $conversion)
                <div class="card">
                    <div class="card-header pt-2 pb-0">
                        <h6>{{ $conversion->replyBy()->name }}
                            <small>({{ $conversion->created_at->diffForHumans() }})</small>
                        </h6>
                    </div>
                    <div class="card-body pt-2 pb-0">
                        <div>{!! $conversion->description !!}</div>
                        @php $attachments = json_decode($conversion->attachments); @endphp
                        @if (count($attachments))
                            <div class="m-1">
                                <h6>{{ __('Attachments') }} :</h6>
                                <ul class="list-group list-group-flush">
                                    @foreach ($attachments as $index => $attachment)
                                        <li class="list-group-item px-0">
                                            {{ $attachment->name }}
                                            <a href="javascript:void(0);"
                                               onclick="openAttachment('{{ get_file($attachment->path) }}')"
                                               class="edit-icon py-1 ml-2"
                                               title="Preview">
                                               <i class="fas fa-eye ms-2"></i>
                                            </a>
                                            <a href="{{ get_file($attachment->path) }}"
                                               class="edit-icon py-1 ml-2"
                                               title="{{ __('Download') }}"
                                               download>
                                                <i class="fas fa-download ms-2"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($canReply)
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="row">
                    @permission('helpdesk ticket reply')
                        <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                            <div class="card">
                                <div class="card-header pt-3 pb-3">
                                    <h6>{{ __('Add Reply') }}</h6>
                                </div>
                                <form method="post" id="SummernoteForm" action="{{ route('helpdesk-ticket.conversion.store', $ticket->id) }}"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="card-body">
                                        <div class="form-group">
                                            <textarea name="reply_description" class="form-control summernote" id="reply_description"></textarea>
                                            <div class="invalid-feedback d-block ">
                                                {{ $errors->first('reply_description') }}
                                            </div>
                                        </div>

                                        <p class="text-danger d-none" id="skill_validation">{{ __('Description filed is required.') }}</p>
                                        <div class="form-group file-group">
                                            <label class="require form-label">{{ __('Attachments') }}</label>
                                            <label class="form-label"><small>({{ __('You can select multiple files') }})</small></label>
                                            <div class="choose-file">
                                                <label for="file" class="form-label d-block">
                                                    <input type="file" name="reply_attachments[]" id="file"
                                                        class="form-control mb-2 {{ $errors->has('reply_attachments') ? ' is-invalid' : '' }}"
                                                        multiple data-filename="multiple_reply_file_selection"
                                                        onchange="document.getElementById('blah') && (document.getElementById('blah').src = window.URL.createObjectURL(this.files[0]))">

                                                    <div class="invalid-feedback">
                                                        {{ $errors->first('reply_attachments.*') }}
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <p class="multiple_reply_file_selection"></p>
                                        <div class="text-end">
                                            <button class="btn btn-primary btn-block mt-2 btn-submit"
                                                type="submit" id="save">{{ __('Submit') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                            <div class="card">
                                <div class="card-header pt-3 pb-3">
                                    <h6>{{ __('Notes') }}</h6>
                                </div>
                                <form method="post" id="notesform" action="{{ route('helpdesk-ticket.note.store', $ticket->id) }}">
                                    @csrf
                                    <div class="card-body adjust_card_width">
                                        <div class="form-group ckfix_height">
                                            <textarea name="note" class="form-control summernote" id="note">{{ $ticket->note }}</textarea>

                                            <div class="invalid-feedback">
                                                {{ $errors->first('note') }}
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button class="btn btn-primary btn-block mt-2 btn-submit"
                                                type="submit">{{ __('Add Note') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endpermission
                </div>
            </div>
        @endif

        <a href="{{ route('helpdesk.index') }}" class="btn btn-dark btn-sm btn-tall">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Tickets') }}
        </a>
    </div>

    <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('helpdesk.reassign', $ticket->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Reassign Ticket') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Assigned To') }}</label>
                            <select name="assigned_user_id" class="form-control" required>
                                <option value="">{{ __('Select user') }}</option>
                                @foreach($assignableUsers as $u)
                                    <option value="{{ $u->id }}" @selected((int)$ticket->user_id === (int)$u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Comment') }}</label>
                            <textarea name="comment" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.js') }}"></script>

<script>
    $("#SummernoteForm").submit(function(e) {
        var desc = $("#reply_description").val();
        if (!isNaN(desc)) {
            $('#skill_validation').removeClass('d-none')
            event.preventDefault();
        } else {
            $('#skill_validation').addClass('d-none')
        }
    });
</script>

<script>
    $("#notesform").submit(function(e) {
        var desc = $("#notesform iframe").val().find("body").text();
        if (!isNaN(desc)) {
            $('#note_validation').removeClass('d-none')
            event.preventDefault();
        } else {
            $('#note_validation').addClass('d-none')
        }
    });
</script>

<script>
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
                if (data.email) {
                    $('.emailAddressField').val(data.email);
                    $('.emailAddressField').prop('readonly', true);
                    $('.emailAddressField').css('background-color', '#e9ecef');
                } else {
                    $('.emailAddressField').val('');
                    $('.emailAddressField').prop('readonly', false);
                    $('.emailAddressField').css('background-color', '');
                }
            }
        });
    });
</script>

<script>
    const $taskSel = $('#task_project_select');
    const $custSel = $('#customer_select');
    const $clientSel = $('#customer_client_select');
    const ajaxClientsUrlBase = @json(route('helpdesk.ajax.customer-clients', ['customer' => '___ID___']));
    const allCustomerOptionsHtml = $custSel.length ? $custSel.html() : '';

    function loadCustomerClients(customerId, selectedId) {
        if (!$clientSel.length) return;

        if (!customerId) {
            $clientSel.html('<option value="">— Select Client —</option>');
            return;
        }

        const url = ajaxClientsUrlBase.replace('___ID___', customerId);
        $.getJSON(url, function(list) {
            let html = '<option value="">— Select Client —</option>';
            $.each(list, function(_, item) {
                html += '<option value="' + item.id + '">' + item.name + '</option>';
            });
            $clientSel.html(html);
            if (selectedId) $clientSel.val(String(selectedId));
        });
    }

    function onTaskChange() {
        if (!$taskSel.length || !$custSel.length) return;

        const val = $taskSel.val();

        if (!val) {
            $custSel.prop('disabled', false).trigger('change');
            $('#customer_id_hidden').prop('disabled', true);
            $custSel.html(allCustomerOptionsHtml);
            return;
        }

        const url = "{{ route('helpdesk.ajax.customers-by-task', ['taskId' => 'TASK_ID']) }}".replace('TASK_ID', val);

        $.getJSON(url, function(list) {
            let html = '<option value="">— Select Customer —</option>';
            list.forEach(function(it) {
                html += '<option value="' + it.id + '">' + it.name + '</option>';
            });

            if (list.length === 1) {
                $custSel.html(html);
                $custSel.val(String(list[0].id)).prop('disabled', true).trigger('change');
                $('#customer_id_hidden').val(String(list[0].id)).prop('disabled', false);
            } else {
                $custSel.html(allCustomerOptionsHtml);
                $custSel.prop('disabled', false).trigger('change');
                $('#customer_id_hidden').prop('disabled', true);
            }
        });
    }

    function onCustomerChange() {
        if (!$custSel.length) return;

        const custId = $custSel.val();
        $('#customer_id_hidden').val(custId || '');
        loadCustomerClients(custId, null);
    }

    $taskSel.on('change', onTaskChange);
    $custSel.on('change', onCustomerChange);

    $(function() {
        onTaskChange();

        const presetCustomer = $custSel.val();
        const presetClient = @json(old('customer_client_id', $ticket->customer_client_id ?? ''));
        if (presetCustomer) {
            loadCustomerClients(presetCustomer, presetClient);
        }
    });
</script>
<script>
    const parentTicketsMap = @json(collect($parentTickets)->keyBy('id'));
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
    function openAttachment(url) {
        const frame = document.getElementById('attachmentFrame');
        if (!frame) return;

        frame.src = url;

        const modalEl = document.getElementById('attachmentModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target && event.target.id === 'attachmentModal') {
            const frame = document.getElementById('attachmentFrame');
            if (frame) {
                frame.src = '';
            }
        }
    });
</script>
@endpush

@push('css')
<style>
    .attachment_list li {
        list-style: none;
        display: inline;
    }
</style>
@endpush


<div class="modal fade" id="attachmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height:90vh;">
            <div class="modal-header">
                <h5 class="modal-title">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height:100%;">
                <iframe id="attachmentFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

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