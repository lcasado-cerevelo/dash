@php
  $isOwner = $timesheet->user_id === Auth::id() || $timesheet->created_by === Auth::id();
  $isSuper = Auth::user()->hasRole('super admin');
@endphp


@if (Laratrust::hasPermission('timesheet edit') || Laratrust::hasPermission('timesheet delete'))
    <td scope="col" class="">
        @permission('timesheet edit')
            <div class="action-btn me-2">
                <a data-url="{{ route('timesheet.edit', $timesheet->id) }}" data-size="lg" data-ajax-popup="true"
                    data-bs-toggle="tooltip" data-title="{{ __('Edit Timesheet') }}" title="{{ __('Edit') }}"
                    class="btn btn-sm text-white bg-info">
                    <i class="ti ti-pencil"></i>
                </a>
                @if($isOwner || $isSuper)
                @else
                    <a data-url="{{ route('timesheet.view', $timesheet->id) }}" data-size="lg" data-ajax-popup="true"
                        data-bs-toggle="tooltip" data-title="{{ __('View Timesheet') }}" title="{{ __('View') }}"
                        class="btn btn-sm text-white bg-info">
                        <i class="ti ti-eye"></i>
                    </a>
    
                @endif
            </div>
        @endpermission
        @permission('timesheet delete')
            <div class="action-btn">
                {!! Form::open(['method' => 'DELETE', 'route' => ['timesheet.destroy', $timesheet->id]]) !!}
                <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para show_confirm bg-danger"
                    data-bs-toggle="tooltip" title="" data-bs-original-title="{{ __('Delete') }}" aria-label="Delete"
                    data-confirm="{{ __('Are You Sure?') }}"
                    data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                    data-confirm-yes="delete-form-{{  $timesheet->id }}"><i class="text-white ti ti-trash"></i></a>
                {!! Form::close() !!}
            </div>
        @endpermission
    </td>
@endif
