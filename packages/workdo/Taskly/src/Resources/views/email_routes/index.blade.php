{{-- Modules/Taskly/Resources/views/email_routes/index.blade.php --}}
@extends('layouts.main') {{-- ajusta al layout que use Taskly, por ejemplo "taskly::layouts.main" --}}

@section('page-title', 'Rutas de correo a proyectos')

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success my-2">
            {{ session('success') }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Nueva ruta de correo → Proyecto</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('taskly.email_routes.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Mailbox</label>
                        <select name="mailbox_id" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            @foreach($mailboxes as $mb)
                                <option value="{{ $mb->id }}">{{ $mb->name }} ({{ $mb->username }})</option>
                            @endforeach
                        </select>
                        @error('mailbox_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Proyecto</label>
                        <select name="project_id" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Email a monitorear (TO)</label>
                        <input type="email" name="to_email" class="form-control" value="{{ old('to_email') }}" required>
                        @error('to_email') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_new_ticket" id="allow_new_ticket" checked>
                            <label class="form-check-label" for="allow_new_ticket">
                                Permitir crear tareas nuevas
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Activo
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6 text-end">
                        <button type="submit" class="btn btn-primary">
                            Guardar ruta
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla con rutas existentes --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Rutas configuradas</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mailbox</th>
                        <th>Email (TO)</th>
                        <th>Proyecto</th>
                        <th>Permite nuevas</th>
                        <th>Activo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routes as $route)
                        <tr>
                            <td>{{ $route->id }}</td>
                            <td>{{ optional($route->mailbox)->name }}<br>
                                <small class="text-muted">{{ optional($route->mailbox)->username }}</small>
                            </td>
                            <td>{{ $route->to_email }}</td>
                            <td>{{ optional($route->project)->name }}</td>
                            <td>{{ $route->allow_new_ticket ? 'Sí' : 'No' }}</td>
                            <td>{{ $route->is_active ? 'Sí' : 'No' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('taskly.email_routes.destroy', $route) }}"
                                      onsubmit="return confirm('¿Eliminar esta ruta?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No hay rutas configuradas todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
