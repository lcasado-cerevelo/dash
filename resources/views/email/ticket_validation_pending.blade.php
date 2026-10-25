@extends('email.common')
@section('content')
    <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#212529;">
    <h2 style="margin:0 0 8px 0;">Tickets que enviaste a validar y siguen sin validarse</h2>
    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }}, tú pasaste estos tickets a <strong>Validation</strong> y todavía
        no han sido validados por la persona asignada ({{ $items->count() }}).
    </p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#f1f3f5;text-align:left;">
                <th style="border:1px solid #dee2e6;">Ticket</th>
                <th style="border:1px solid #dee2e6;">Asunto</th>
                <th style="border:1px solid #dee2e6;">Debe validar</th>
                <th style="border:1px solid #dee2e6;">En Validation desde</th>
                <th style="border:1px solid #dee2e6;">Días</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;"><a href="{{ $svc->ticketUrl($item['ticket']) }}">#{{ $item['ticket']->ticket_id }}</a></td>
                    <td style="border:1px solid #dee2e6;">{{ $item['ticket']->subject }}</td>
                    <td style="border:1px solid #dee2e6;">{{ $item['assignee']->name ?? '—' }}</td>
                    <td style="border:1px solid #dee2e6;white-space:nowrap;">{{ optional($item['since'])->format('d/m/Y') }}</td>
                    <td style="border:1px solid #dee2e6;text-align:center;">{{ $item['days'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
@endsection
