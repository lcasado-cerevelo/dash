<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Validar ticket</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background:#f1f3f5; margin:0; padding:24px; color:#212529; }
        .card { max-width:560px; margin:40px auto; background:#fff; border-radius:8px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin:0 0 12px; }
        .meta { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:12px; margin:16px 0; font-size:14px; }
        button { background:#198754; color:#fff; border:0; border-radius:6px; padding:12px 28px; font-size:16px; font-weight:bold; cursor:pointer; }
        .muted { color:#6c757d; font-size:13px; }
    </style>
</head>
<body>
<div class="card">
    @if ($state === 'confirm')
        <h1>¿Validar y cerrar este ticket?</h1>
        <div class="meta">
            <strong>#{{ $ticket->ticket_id }}</strong> — {{ $ticket->subject }}<br>
            <span class="muted">Se registrará como validado por {{ $user->name }}.</span>
        </div>
        <form method="POST" action="{{ request()->getRequestUri() }}">
            @csrf
            <button type="submit">Confirmar validación</button>
        </form>
        <p class="muted">Si el ticket aún no está listo, simplemente cierra esta página.</p>
    @elseif ($state === 'done')
        <h1>✔ Ticket validado</h1>
        <p><strong>#{{ $ticket->ticket_id }}</strong> — {{ $ticket->subject }}</p>
        <p class="muted">El ticket quedó cerrado. Ya puedes cerrar esta página.</p>
    @elseif ($state === 'already')
        <h1>Este ticket ya fue validado</h1>
        <p class="muted">El ticket #{{ $ticket->ticket_id }} ya no está pendiente de validación; este enlace ya no tiene efecto.</p>
    @elseif ($state === 'superseded')
        <h1>Este enlace ya no es válido</h1>
        <p class="muted">El ticket volvió a enviarse a validación desde que se generó este enlace. Use el enlace del correo más reciente o entre al sistema.</p>
    @elseif ($state === 'reassigned')
        <h1>Este enlace ya no es válido</h1>
        <p class="muted">El ticket fue reasignado a otra persona. Entre al sistema para gestionarlo.</p>
    @elseif ($state === 'expired')
        <h1>Enlace vencido o inválido</h1>
        <p class="muted">Este botón dura {{ config('ticket_notify.link_days') }} días. Entre al sistema para validar el ticket; recibirá un nuevo recordatorio con un enlace vigente.</p>
    @else
        <h1>Ticket no encontrado</h1>
        <p class="muted">Entre al sistema para gestionarlo.</p>
    @endif
</div>
</body>
</html>
