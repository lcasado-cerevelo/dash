{{-- Modal: Gestión de Workspaces del Usuario --}}
{{-- Incluir en users/index.blade.php o llamar vía ruta dedicada --}}

<div class="modal fade" id="userWorkspacesModal-{{ $user->id }}" tabindex="-1"
     aria-labelledby="userWorkspacesModalLabel-{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="userWorkspacesModalLabel-{{ $user->id }}">
                    <i class="ti ti-layout-grid me-1"></i>
                    {{ __('Workspaces') }}: <strong>{{ $user->name }}</strong>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>

            <div class="modal-body">

                {{-- Workspaces asignados actualmente --}}
                <h6 class="mb-3 text-muted">{{ __('Current Workspaces') }}</h6>

                @php
                    $userWorkspaces = $user->workspaces()->orderBy('work_spaces.name')->get();
                @endphp

                @if($userWorkspaces->isEmpty())
                    <div class="alert alert-warning py-2">
                        {{ __('This user is not assigned to any workspace.') }}
                    </div>
                @else
                    <ul class="list-group mb-4">
                        @foreach($userWorkspaces as $ws)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="ti ti-building me-1 text-primary"></i>
                                    {{ $ws->name }}
                                    @if($user->active_workspace == $ws->id)
                                        <span class="badge bg-success ms-2">{{ __('Active') }}</span>
                                    @endif
                                    @if($ws->pivot->is_default)
                                        <span class="badge bg-secondary ms-1">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                @if($userWorkspaces->count() > 1)
                                    <form method="POST"
                                          action="{{ route('users.remove-workspace', [$user->id, $ws->id]) }}"
                                          onsubmit="return confirm('{{ __('Remove this user from the workspace?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Asignar a nuevo workspace --}}
                @php
                    $assignedIds   = $userWorkspaces->pluck('id')->toArray();
                    $available     = \App\Models\WorkSpace::where('created_by', creatorId())
                                        ->where('is_disable', 1)
                                        ->whereNotIn('id', $assignedIds)
                                        ->orderBy('name')
                                        ->get();
                @endphp

                @if($available->isNotEmpty())
                    <h6 class="mb-2 text-muted">{{ __('Assign to Another Workspace') }}</h6>
                    <form method="POST" action="{{ route('users.assign-workspace', $user->id) }}">
                        @csrf
                        <div class="input-group">
                            <select name="workspace_id" class="form-control" required>
                                <option value="">{{ __('Select workspace…') }}</option>
                                @foreach($available as $ws)
                                    <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>{{ __('Assign') }}
                            </button>
                        </div>
                    </form>
                @else
                    <p class="text-muted small mt-2">
                        {{ __('No more workspaces available to assign.') }}
                    </p>
                @endif

            </div>{{-- /.modal-body --}}

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    {{ __('Close') }}
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Botón disparador (colocarlo en la fila de la tabla de usuarios) --}}
{{-- Ejemplo de uso en users/index.blade.php o action.blade.php:
<button type="button"
        class="btn btn-sm btn-outline-primary"
        data-bs-toggle="modal"
        data-bs-target="#userWorkspacesModal-{{ $user->id }}"
        title="{{ __('Manage Workspaces') }}">
    <i class="ti ti-layout-grid"></i>
</button>

@include('users.workspace_modal', ['user' => $user])
--}}
