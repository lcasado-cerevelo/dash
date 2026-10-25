@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    @php
        $name = fn ($id) => $week['users']->get((int) $id)->name ?? '—';
        $th   = 'border:1px solid #dee2e6;background:#f1f3f5;text-align:left;padding:6px;';
        $td   = 'border:1px solid #dee2e6;padding:6px;';
    @endphp

    <h2 style="margin:0 0 8px 0;">Resumen semanal de tickets</h2>
    <p style="margin:0 0 16px 0;">
        Del {{ $week['start']->format('d/m/Y') }} al {{ $week['end']->format('d/m/Y') }}.
        Hoy hay <strong>{{ $week['open_now'] }}</strong> ticket(s) abiertos en total.
    </p>

    {{-- Creados --}}
    <h3 style="margin:20px 0 6px 0;">Creados ({{ $week['created']->count() }})</h3>
    @if ($week['created']->isEmpty())
        <p style="margin:0;color:#6c757d;">Ninguno esta semana.</p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
            <tr>
                <th style="{{ $th }}">Ticket</th><th style="{{ $th }}">Asunto</th>
                <th style="{{ $th }}">Asignado a</th><th style="{{ $th }}">Estado</th><th style="{{ $th }}">Creado</th>
            </tr>
            @foreach ($week['created'] as $t)
                <tr>
                    <td style="{{ $td }}white-space:nowrap;"><a href="{{ $svc->ticketUrl($t) }}">#{{ $t->ticket_id }}</a></td>
                    <td style="{{ $td }}">{{ $t->subject }}</td>
                    <td style="{{ $td }}">{{ $name($t->user_id) }}</td>
                    <td style="{{ $td }}white-space:nowrap;">{{ $t->status }}</td>
                    <td style="{{ $td }}white-space:nowrap;">{{ optional($t->created_at)->format('d/m') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Enviados a validar --}}
    <h3 style="margin:24px 0 6px 0;">Enviados a validar ({{ $week['validation']->count() }})</h3>
    @if ($week['validation']->isEmpty())
        <p style="margin:0;color:#6c757d;">Ninguno esta semana.</p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
            <tr>
                <th style="{{ $th }}">Ticket</th><th style="{{ $th }}">Asunto</th>
                <th style="{{ $th }}">Lo envió</th><th style="{{ $th }}">Debe validar</th>
                <th style="{{ $th }}">Estado hoy</th><th style="{{ $th }}">Fecha</th>
            </tr>
            @foreach ($week['validation'] as $m)
                @php($t = $m['ticket'])
                <tr>
                    <td style="{{ $td }}white-space:nowrap;"><a href="{{ $svc->ticketUrl($t) }}">#{{ $t->ticket_id }}</a></td>
                    <td style="{{ $td }}">{{ $t->subject }}</td>
                    <td style="{{ $td }}">{{ $name($m['by']) }}</td>
                    <td style="{{ $td }}">{{ $name($t->user_id) }}</td>
                    <td style="{{ $td }}white-space:nowrap;">{{ $t->status }}</td>
                    <td style="{{ $td }}white-space:nowrap;">{{ optional($m['when'])->format('d/m') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Cerrados --}}
    <h3 style="margin:24px 0 6px 0;">Cerrados ({{ $week['closed']->count() }})</h3>
    @if ($week['closed']->isEmpty())
        <p style="margin:0;color:#6c757d;">Ninguno esta semana.</p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
            <tr>
                <th style="{{ $th }}">Ticket</th><th style="{{ $th }}">Asunto</th>
                <th style="{{ $th }}">Cerrado por</th><th style="{{ $th }}">Fecha</th>
            </tr>
            @foreach ($week['closed'] as $m)
                @php($t = $m['ticket'])
                <tr>
                    <td style="{{ $td }}white-space:nowrap;"><a href="{{ $svc->ticketUrl($t) }}">#{{ $t->ticket_id }}</a></td>
                    <td style="{{ $td }}">{{ $t->subject }}</td>
                    <td style="{{ $td }}">{{ $name($m['by']) }}</td>
                    <td style="{{ $td }}white-space:nowrap;">{{ optional($m['when'])->format('d/m') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    </div>
@endsection
