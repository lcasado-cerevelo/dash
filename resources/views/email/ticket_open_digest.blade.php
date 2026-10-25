@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    <h2 style="margin:0 0 8px 0;">Tickets abiertos asignados a {{ $user->name }}</h2>
    <p style="margin:0 0 16px 0;">Resumen semanal ({{ now()->format('d/m/Y') }}): {{ $tickets->count() }} ticket(s) sin cerrar.</p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#f1f3f5;text-align:left;">
                <th style="border:1px solid #dee2e6;">Ticket</th>
                <th style="border:1px solid #dee2e6;">Asunto</th>
                <th style="border:1px solid #dee2e6;">Estado</th>
                <th style="border:1px solid #dee2e6;">Prioridad</th>
                <th style="border:1px solid #dee2e6;">Creado</th>
                <th style="border:1px solid #dee2e6;">Último movimiento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tickets as $ticket)
                <tr>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;"><a href="{{ $svc->ticketUrl($ticket) }}">#{{ $ticket->ticket_id }}</a></td>
                    <td style="border:1px solid #dee2e6;">{{ $ticket->subject }}</td>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;">{{ $ticket->status }}</td>
                    <td style="border:1px solid #dee2e6;text-align:center;">{{ $ticket->priority }}</td>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;">{{ optional($ticket->created_at)->format('d/m/Y') }}</td>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;">{{ optional($ticket->updated_at)->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
@endsection
