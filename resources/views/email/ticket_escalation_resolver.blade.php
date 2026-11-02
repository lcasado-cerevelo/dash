@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    @php
        $th = 'border:1px solid #dee2e6;background:#f1f3f5;text-align:left;padding:6px;';
        $td = 'border:1px solid #dee2e6;padding:6px;';
    @endphp

    <h2 style="margin:0 0 8px 0;color:#b02a37;">Tickets que enviaste a validar siguen sin validarse</h2>
    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }}, tú enviaste estos {{ $items->count() }} ticket(s) a <strong>Validation</strong> y llevan
        <strong>más de {{ $days }} días</strong> sin que la persona asignada los valide. Ella recibe un recordatorio
        diario; este aviso es para que le des seguimiento. Lo recibirás todos los días mientras sigan pendientes.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
        <tr>
            <th style="{{ $th }}">Ticket</th>
            <th style="{{ $th }}">Asunto</th>
            <th style="{{ $th }}">Debe validar</th>
            <th style="{{ $th }}">En Validation desde</th>
            <th style="{{ $th }}">Días</th>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td style="{{ $td }}white-space:nowrap;"><a href="{{ $svc->ticketUrl($item['ticket']) }}">#{{ $item['ticket']->ticket_id }}</a></td>
                <td style="{{ $td }}">{{ $item['ticket']->subject }}</td>
                <td style="{{ $td }}">{{ $item['assignee']->name ?? '—' }}</td>
                <td style="{{ $td }}white-space:nowrap;">{{ optional($item['since'])->format('d/m/Y') }}</td>
                <td style="{{ $td }}text-align:center;">{{ $item['days'] }}</td>
            </tr>
        @endforeach
    </table>
    </div>
@endsection
