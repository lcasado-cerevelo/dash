{{-- Timesheets relacionados --}}
<div class="card shadow-sm border-0 mt-4" id="ts-related-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="ti ti-clock mr-1"></i> {{ __('Related Timesheets') }}
        </h6>
        <span class="badge bg-primary">{{ $timesheets->count() }}</span>
    </div>

    <div class="card-body p-0 w-100">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="tbl-timesheets-related">
                <colgroup>
                    <col style="width:40%">   {{-- User --}}
                    <col style="width:25%">   {{-- Service Date --}}
                    <col style="width:20%">   {{-- Total Hours --}}
                    <col style="width:15%">   {{-- Actions --}}
                </colgroup>                
                <thead class="table-light">
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Service Date') }}</th>
                        <th class="text-end">{{ __('Total Hours') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                @php
                    // (Opcional) formateo helper local
                    $fmt = fn($h,$m) => sprintf('%02d:%02d', (int)$h, (int)$m);
                @endphp

                @forelse($timesheets as $ts)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <img
                                alt="avatar"
                                class="rounded-circle me-2"
                                width="28" height="28"
                                src="{{ $ts->user?->avatar ? get_file($ts->user->avatar) : get_file('avatar.png') }}">
                            <div>
                                <div class="fw-semibold">{{ $ts->user?->name }}</div>
                                <small class="text-muted">{{ $ts->user?->email }}</small>
                            </div>
                        </td>

                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ \Carbon\Carbon::parse($ts->date)->format('Y-m-d') }}
                            </span>
                        </td>

                        <td class="text-end">
                            <span class="fw-semibold">{{ $fmt($ts->hours, $ts->minutes) }}</span>
                        </td>

                        <td class="text-end">
                            @if(
                                \Laratrust::hasRole('super admin')
                                || \Laratrust::hasPermission('timesheet validate')
                                || \Laratrust::hasPermission('timesheet unvalidate')
                                || auth()->id() === $ts->user_id
                            )
                                <a href="{{ route('timesheet.show', $ts->id) }}"
                                   class="btn btn-sm btn-light border"
                                   data-ajax-popup="true" data-size="lg"
                                   data-title="{{ __('Timesheet detail') }}">
                                    <i class="ti ti-eye"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="ti ti-inbox me-1"></i> {{ __('No timesheets yet') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>

                @php
                    // Totalizador en el pie de tabla
                    $totalMinutes = $timesheets->sum(fn($t) => ((int)$t->hours)*60 + (int)$t->minutes);
                    $th = intdiv($totalMinutes, 60);
                    $tm = $totalMinutes % 60;
                @endphp
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end">{{ __('Total') }}</th>
                        <th class="text-end">{{ sprintf('%02d:%02d', $th, $tm) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Micro-estilos para darle cariño sin romper el tema --}}
<style>
#ts-related-card .table thead th { position: sticky; top: 0; z-index: 1; }
#ts-related-card .table td, #ts-related-card .table th { vertical-align: middle; }
#ts-related-card .badge.bg-light { background: #f8f9fa!important; }
#ts-related-card .table{
  width:100%;
  table-layout:fixed;             /* evita que el contenido “achique” la tabla */
}
#ts-related-card .table th,
#ts-related-card .table td{
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
#ts-related-card .table td .fw-semibold{ white-space:normal; }
</style>
