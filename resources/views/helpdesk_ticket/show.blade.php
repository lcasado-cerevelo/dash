@extends('helpdesk_ticket.master')
@section('page-title')
    {{ __('Ticket') }} - {{ $ticket->ticket_id }}
@endsection
@push('css')
    <link href="{{  asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.css')  }}" rel="stylesheet">
    <style>
        /* TITULOS IZQ Y CENTRO */
        .ticket-meta-title {
            font-size: 18px;
        }
        
        /* CENTRO MAS GRANDE */
        .ticket-meta-center .small {
            font-size: 14px;
        }
        
        /* IZQUIERDA igual que centro */
        .ticket-meta-left {
            font-size: 14px;
        }
        
        /* DERECHA MÁS COMPACTA */
        .ticket-meta-right {
            font-size: 13px;
        }
        
        .ticket-meta-right .badge {
            padding: 4px 10px !important;
            font-size: 12px;
        }
        
        /* Reduce spacing vertical derecha */
        .ticket-meta-right > div {
            margin-bottom: 4px !important;
        }
        
        /* Opcional: alinear mejor visualmente */
        .ticket-meta-right span.fw-semibold {
            font-size: 12px;
        }  
    </style>
@endpush

@section('content')
    <div class="auth-wrapper create-ticket ticket-desc justify-content-between flex-column auth-v1">
        <div class="bg-auth-side"></div>
        <div class="auth-content">
            <div class="row align-items-center justify-content-center text-start">
                <div class="col-12">
                    <div class="card rounded-4">
                        <div class="ticket-title text-center bg-primary">
                            <h4 class="text-white mb-0">{{ __('Ticket') }} - {{ $ticket->ticket_id }}</h4>
                        </div>
                       <div class="card-body w-100 p-sm-4 p-3">
                        @csrf
                        @php
                            $status = (string) $ticket->status;
                            $statusLower = strtolower($status);
                            // Mapea a las mismas clases que usas en index (warning / danger / success)
                            $statusClass = $statusLower === 'in progress' ? 'bg-warning'
                                         : ($statusLower === 'on hold' ? 'bg-danger' : 'bg-success');
                        
                            $catName  = optional($ticket->tcategory)->name ?? $ticket->category_name ?? '—';
                            $catColor = optional($ticket->tcategory)->color ?? $ticket->color ?? '#6c757d';
                        @endphp
                        
                        @php
                            $status = (string) $ticket->status;
                            $statusClass = match ($status) {
                                'In Progress' => 'bg-warning text-dark',
                                'On Hold' => 'bg-danger',
                                'Validation' => 'bg-info',
                                'Closed' => 'bg-dark',
                                default => 'bg-success',
                            };
                        
                            $catName = optional($ticket->tcategory)->name ?? $ticket->category_name ?? '—';
                        
                            $priorityLabels = [
                                1 => 'Very Low',
                                2 => 'Low',
                                3 => 'Regular',
                                4 => 'High',
                                5 => 'Very High',
                            ];
                        @endphp
                        
                        <div class="ticket-meta row align-items-start justify-content-between g-3 mb-3">
                            {{-- IZQUIERDA --}}
                            <div class="col-12 col-md-4 text-start ticket-meta-left">
                                <div class="fw-semibold ticket-meta-title">{!! html_entity_decode($ticket->name) !!}</div>                            
                                <div class="text-muted small">
                                    {{ e($ticket->email) }}
                                    <span class="mx-1">•</span>
                                    {{ $ticket->created_at->diffForHumans() }}
                                </div>
                            </div>
                        
                            {{-- CENTRO --}}
                            <div class="col-12 col-md-4 text-center ticket-meta-center">
                                <div class="mt-1 small">
                                    <span class="fw-semibold">{{ __('Customer / Client') }}:</span>
                                    {{ e(optional($ticket->customer)->name ?? '—') }}
                                    @if($ticket->customerClient)
                                        <span class="mx-1">/</span>{{ e($ticket->customerClient->name) }}
                                    @endif
                                </div>
                        
                                @php
                                    $taskTitle = optional($ticket->task)->title;
                                    $projectName = data_get($ticket, 'task.project.name');
                                @endphp
                        
                                @if($taskTitle || $projectName)
                                    <div class="mt-1 small">
                                        <span class="fw-semibold">{{ __('Task / Project') }}:</span>
                                        {{ e($taskTitle ?? '—') }}{{ ($taskTitle && $projectName) ? ' / ' : '' }}{{ e($projectName ?? '') }}
                                    </div>
                                @endif
                        
                                <div class="mt-1 small">
                                    <span class="fw-semibold">{{ __('Subject') }}:</span>
                                    {{ $ticket->subject }}
                                </div>
                        
                                <div class="mt-1 small">
                                    <span class="fw-semibold">{{ __('Assigned') }}:</span>
                                    {{ optional($ticket->user)->name ?? '---' }}
                                </div>
                            </div>
                        
                            {{-- DERECHA --}}
                            <div class="col-12 col-md-4 text-md-end text-start ticket-meta-right">
                                <div class="mb-2 d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold">{{ __('Status') }}:</span>
                                    <span class="badge fix_badge p-2 px-3 {{ $statusClass }}">{{ __($status) }}</span>
                                </div>
                        
                                <div class="mb-2 d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold">{{ __('Category') }}:</span>
                                    <span class="badge bg-primary fix_badge p-2 px-3">{{ e($catName) }}</span>
                                </div>
                        
                                <div class="d-flex justify-content-md-end justify-content-start align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold">{{ __('Priority') }}:</span>
                                    <span class="badge bg-info fix_badge p-2 px-3">
                                        {{ $priorityLabels[(int)$ticket->priority] ?? 'Regular' }}
                                    </span>
                                </div>
                            </div>
                        </div>                       
                        <div class="card mb-3">
                            <div class="card-header p-3">
                                <h6 class="mb-0">{!! html_entity_decode($ticket->name) !!}
                                    <small>({{ $ticket->created_at->diffForHumans() }})</small></h6>
                            </div>
                            <div class="card-body p-3 w-100">
                                <div>
                                    <p>{!! $ticket->description !!}</p>
                                </div>
                                @php
                                    $attachments = json_decode($ticket->attachments);
                                @endphp
                                @if (!is_null($attachments) && count($attachments) > 0)
                                    <div>
                                        <b>{{ __('Attachments') }} :</b>
                                        <ul class="list-group list-group-flush">
                                            @foreach ($attachments as $index => $attachment)
                                                <li class="list-group-item pb-0 px-0">
                                                    {{ $attachment->name }}
                                
                                                    <a href="javascript:void(0);"
                                                        onclick="openAttachment('{{ get_file($attachment->path) }}')"
                                                        class="edit-icon py-1 ml-2"
                                                        title="{{ __('Preview') }}">
                                                        <i class="fas fa-eye ms-2"></i>
                                                    </a>
                                
                                                    <a download="{{ $attachment->name }}"
                                                        href="{{ get_file($attachment->path) }}"
                                                        class="edit-icon py-1 ml-2"
                                                        title="{{ __('Download') }}">
                                                        <i class="fa fa-download ms-2"></i>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @foreach ($ticket->conversions as $conversion)
                            <div class="card mb-3">
                                <div class="card-header p-3">
                                    <h6 class="mb-0">{{ $conversion->replyBy()->name }}
                                        <small>({{ $conversion->created_at->diffForHumans() }})</small>
                                    </h6>
                                </div>
                                <div class="card-body p-3 w-100">
                                    <div>{!! $conversion->description !!}</div>
                                    @php
                                        $attachments = json_decode($conversion->attachments);
                                    @endphp
                                    @if (count($attachments))
                                        <div class="m-1">
                                            <b>{{ __('Attachments') }} :</b>
                                            <ul class="list-group list-group-flush">
                                                @foreach ($attachments as $index => $attachment)
                                                    <li class="list-group-item px-0">
                                                        {{ $attachment->name }}
                                    
                                                        <a href="javascript:void(0);"
                                                            onclick="openAttachment('{{ get_file($attachment->path) }}')"
                                                            class="edit-icon py-1 ml-2"
                                                            title="{{ __('Preview') }}">
                                                            <i class="fas fa-eye ms-2"></i>
                                                        </a>
                                    
                                                        <a download="{{ $attachment->name }}"
                                                            href="{{ get_file($attachment->path) }}"
                                                            class="edit-icon py-1 ml-2"
                                                            title="{{ __('Download') }}">
                                                            <i class="fa fa-download ms-2"></i>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($ticket->parentTicket)
                            <div class="mb-3">
                                <strong>{{ __('Parent Ticket') }}:</strong>
                                <a href="{{ route('helpdesk.view', [\Illuminate\Support\Facades\Crypt::encrypt($ticket->parentTicket->ticket_id)]) }}">
                                    #{{ $ticket->parentTicket->ticket_id }} - {{ $ticket->parentTicket->subject }}
                                </a>
                            </div>
                        @endif
                        @if($ticket->childTickets && $ticket->childTickets->count())
                            <div class="mb-3">
                                <strong>{{ __('Child Tickets') }}:</strong>
                                <ul class="mb-0">
                                    @foreach($ticket->childTickets as $child)
                                        <li>
                                            <a href="{{ route('helpdesk.view', [\Illuminate\Support\Facades\Crypt::encrypt($child->ticket_id)]) }}">
                                                #{{ $child->ticket_id }} - {{ $child->subject }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif                        
                        @if(false)
                        @if ($ticket->status != 'Closed')
                            <div class="card mb-3">
                                <div class="card-body p-3 w-100">
                                    <form method="post"
                                        action="{{ route('helpdesk-ticket.reply', [$ticket->ticket_id]) }}"
                                        enctype="multipart/form-data" id="ckeditorForm">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-12">
                                                <label class="require form-label">{{ __('Description') }}</label>

                                                <textarea name="reply_description"
                                                    class="form-control summernote  {{ !empty($errors->first('reply_description')) ? 'is-invalid' : '' }}" required
                                                    id="help-desc">{{ old('reply_description') }}</textarea>

                                                <div class="invalid-feedback">
                                                    {{ $errors->first('reply_description') }}
                                                </div>
                                                <p class="text-danger d-none" id="skill_validation">
                                                    {{ __('Description filed is required.') }}</p>
                                            </div>
                                            <div class="form-group col-md-12 file-group">
                                                <label class="require form-label">{{ __('Attachments') }}</label>
                                                <label
                                                    class="form-label"><small>({{ __('You can select multiple files') }})</small></label>
                                                <div class="choose-file form-group mb-0">
                                                    <label for="file" class="form-label mb-0">

                                                        <input type="file"
                                                            class="form-control {{ $errors->has('reply_attachments') ? 'is-invalid' : '' }}"
                                                            multiple="" name="reply_attachments[]" id="file"
                                                            data-filename="multiple_reply_file_selection">
                                                        <div class="invalid-feedback">
                                                            {{ $errors->first('reply_attachments') }}
                                                        </div>
                                                    </label>
                                                    <p class="multiple_reply_file_selection"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-0 col-md-12">
                                            <div class="text-center">
                                                <input type="hidden" name="status" value="In Progress" />
                                                <button
                                                    class="btn btn-submit btn-primary btn-block">{{ __('Submit') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="card">
                                <div class="card-body p-3">
                                    <p class="text-blue font-weight-bold text-center mb-0">
                                        {{ __('Ticket is closed you cannot replay.') }}</p>
                                </div>
                            </div>
                        @endif
                        @endif
                        @if(!empty($canViewTimesheets))                        
                            @include('timesheet::timesheet._list_by_parent', ['timesheets'=>$timesheets])
                        @endif
                       </div>
               

    
        <a href="{{ route('helpdesk.index') }}" class="btn btn-dark btn-sm btn-tall">
            <span class="color-white"><i class="ti ti-arrow-left me-1"></i>{{ __('Back to Tickets') }}</span>
        </a>
                    </div>
                </div>
            </div>

        </div>
 </div>
@endsection
<style>
#tbl-timesheets-related {
    table-layout: auto !important;  /* ← permite que las columnas se ajusten al contenido */
    width: 100%;
    min-width: 600px;               /* ← evita que la tabla se “achique” demasiado */
}

#tbl-timesheets-related th,
#tbl-timesheets-related td {
    white-space: nowrap;            /* evita que se rompa en varias líneas */
    text-overflow: ellipsis;
    overflow: hidden;
    vertical-align: middle;
}

#ts-related-card .table-responsive {
    overflow-x: auto;
}
.btn-tall {
    padding-top: 15px;
    padding-bottom: 15px;
    font-size: 20px;

}
.color-white{
    color: white;
}
</style>
@push('scripts')
<script src="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.js') }}"></script>

    <script>

$("#ckeditorForm").submit(function(e) {
    var desc = $("#help-desc").val().trim();
    if (desc === "" || desc === "<p><br></p>") {
        $('#skill_validation').removeClass('d-none');
        e.preventDefault();
    } else {
        $('#skill_validation').addClass('d-none');
    }
});
    </script>

<script>
    $(document).on('change', 'input[type=file]', function() {
        var names = '';
        var files = $('input[type=file]')[0].files;

        for (var i = 0; i < files.length; i++) {
            names += files[i].name + '<br>';
        }
        $('.' + $(this).attr('data-filename')).html(names);
    });

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

<div class="modal fade" id="attachmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="height:90vh;">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Preview') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height:100%;">
                <iframe id="attachmentFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>