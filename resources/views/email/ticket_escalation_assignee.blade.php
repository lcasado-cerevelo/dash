@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    @php
        $th = 'border:1px solid #dee2e6;background:#f1f3f5;text-align:left;padding:6px;';
        $td = 'border:1px solid #dee2e6;padding:6px;vertical-align:middle;';
    @endphp

    <h2 style="margin:0 0 8px 0;color:#b02a37;">Urgente: tickets pendientes de tu validación</h2>
    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }}, estos {{ $items->count() }} ticket(s) llevan <strong>más de {{ $days }} días</strong> en
        <strong>Validation</strong> esperando que los valides. Recibirás este aviso <strong>todos los días</strong>
        hasta que los valides o los devuelvas.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
        <tr>
            <th style="{{ $th }}">Ticket</th>
            <th style="{{ $th }}">Asunto</th>
            <th style="{{ $th }}">Enviado por</th>
            <th style="{{ $th }}">Días</th>
            <th style="{{ $th }}"></th>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td style="{{ $td }}white-space:nowrap;"><a href="{{ $svc->ticketUrl($item['ticket']) }}">#{{ $item['ticket']->ticket_id }}</a></td>
                <td style="{{ $td }}">{{ $item['ticket']->subject }}</td>
                <td style="{{ $td }}">{{ $item['resolver']->name ?? '—' }}</td>
                <td style="{{ $td }}text-align:center;">{{ $item['days'] }}</td>
                <td style="{{ $td }}text-align:center;">
                    <a href="{{ $svc->validationUrl($item) }}"
                       style="background:#198754;color:#ffffff;text-decoration:none;padding:6px 14px;border-radius:5px;font-weight:bold;display:inline-block;">Validar</a>
                </td>
            </tr>
        @endforeach
    </table>

    <p style="font-size:12px;color:#6c757d;margin-top:16px;">
        Cada botón es de un solo uso, vence en {{ config('ticket_notify.link_days') }} días y te pedirá confirmar antes de cerrar el ticket.
        Si aún no está listo, ábrelo desde el enlace del número y devuélvelo con tu comentario.
    </p>
    </div>
@endsection
