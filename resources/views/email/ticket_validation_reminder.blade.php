@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    @php($ticket = $item['ticket'])
    <h2 style="margin:0 0 8px 0;">Ticket pendiente de tu validación</h2>
    <p style="margin:0 0 16px 0;">
        Hola {{ $item['assignee']->name }}, este ticket lleva <strong>{{ $item['days'] }} días</strong> en
        <strong>Validation</strong> (desde {{ optional($item['since'])->format('d/m/Y') }}){{ ($item['resolver'] && $item['resolver']->id !== $item['assignee']->id) ? ' y fue enviado a validar por ' . $item['resolver']->name : '' }}.
        Si ya está correcto, puedes validarlo con el botón.
    </p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-bottom:16px;">
        <tr><td style="border:1px solid #dee2e6;width:130px;background:#f1f3f5;"><strong>Ticket</strong></td>
            <td style="border:1px solid #dee2e6;"><a href="{{ $svc->ticketUrl($ticket) }}">#{{ $ticket->ticket_id }}</a></td></tr>
        <tr><td style="border:1px solid #dee2e6;background:#f1f3f5;"><strong>Asunto</strong></td>
            <td style="border:1px solid #dee2e6;">{{ $ticket->subject }}</td></tr>
        <tr><td style="border:1px solid #dee2e6;background:#f1f3f5;"><strong>Prioridad</strong></td>
            <td style="border:1px solid #dee2e6;">{{ $ticket->priority }}</td></tr>
        <tr><td style="border:1px solid #dee2e6;background:#f1f3f5;vertical-align:top;"><strong>Descripción</strong></td>
            <td style="border:1px solid #dee2e6;">{{ $svc->plain($ticket->description) ?: '—' }}</td></tr>
        @if ($replies->isNotEmpty())
            <tr><td style="border:1px solid #dee2e6;background:#f1f3f5;vertical-align:top;"><strong>Últimos comentarios</strong></td>
                <td style="border:1px solid #dee2e6;">
                    @foreach ($replies as $r)
                        <div style="margin-bottom:6px;">
                            <em>{{ $r['who'] }} · {{ optional($r['when'])->format('d/m/Y H:i') }}</em><br>
                            {{ $r['text'] }}
                        </div>
                    @endforeach
                </td></tr>
        @endif
    </table>

    <p style="text-align:center;margin:24px 0;">
        <a href="{{ $validateUrl }}"
           style="background:#198754;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;display:inline-block;">
            Validar ticket
        </a>
    </p>
    <p style="font-size:12px;color:#6c757d;">
        El botón es de un solo uso y vence en {{ config('ticket_notify.link_days') }} días. Al abrirlo se te pedirá confirmar
        antes de cerrar el ticket. Si el ticket aún no está listo, ignora este correo: se te recordará de nuevo
        cada {{ config('ticket_notify.reminder_days') }} días.
    </p>
    </div>
@endsection
