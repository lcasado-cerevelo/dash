<div class="modal-body">
    <div class="table-responsive">
        <table class="table">
            <tr role="row">
                <th>{{ __('Title') }}</th>
                <td>{{ !empty($announcement->title) ? $announcement->title : '' }}
                </td>
            </tr>
            <tr>
                <th>{{ __('Branch') }}</th>
                <td>{{ !empty($announcement->branch_id) && !empty($announcement->branch) ? $announcement->branch->name : '' }}
                </td>
            </tr>
            <tr>
                <th>{{ __('Department') }}</th>
                <td class="text-wrap text-break">
                    {{ $announcement->department_list->pluck('name')->implode(', ') }}
                </td>
            </tr>
            <tr>
                <th>{{ __('Employees') }}</th>
                <td class="text-wrap text-break">
                    {{ $announcement->employee_list->pluck('name')->implode(', ') }}
                </td>
            </tr>
            <tr>
                <th>{{ __('Start Date') }}</th>
                <td>{{ !empty($announcement->start_date) ? company_date_formate($announcement->start_date) : '' }}</td>
            </tr>
            <tr>
                <th>{{ __('End Date') }}</th>
                <td>{{ !empty($announcement->end_date) ? company_date_formate($announcement->end_date) : '' }}</td>
            </tr>
        </table>
    </div>
</div>
