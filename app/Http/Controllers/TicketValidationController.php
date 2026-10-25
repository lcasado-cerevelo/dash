<?php

namespace App\Http\Controllers;

use App\Services\TicketNotificationService;
use Illuminate\Http\Request;

/**
 * Validación (cierre) de un ticket desde el botón del correo de recordatorio.
 *
 * El enlace es una URL firmada con vencimiento. Es de un solo uso porque se verifica contra el
 * estado actual del ticket: al validarlo deja de estar en Validation y el enlace deja de servir.
 * GET solo muestra la confirmación (los filtros antivirus abren los enlaces del correo);
 * el cierre ocurre únicamente con el POST del botón "Confirmar".
 */
class TicketValidationController extends Controller
{
    public function __construct(private TicketNotificationService $svc)
    {
    }

    public function show(Request $request, int $ticket, int $audit, int $user)
    {
        if (!$request->hasValidSignature(false)) {
            return $this->page('expired');
        }

        $check = $this->svc->checkLink($ticket, $audit, $user);
        if (!$check['ok']) {
            return $this->page($check['reason'], $check['ticket'] ?? null);
        }

        return $this->page('confirm', $check['ticket'], $check['user']);
    }

    public function confirm(Request $request, int $ticket, int $audit, int $user)
    {
        if (!$request->hasValidSignature(false)) {
            return $this->page('expired');
        }

        $result = $this->svc->validateTicket($ticket, $audit, $user);
        if (!$result['ok']) {
            return $this->page($result['reason'], $result['ticket'] ?? null);
        }

        return $this->page('done', $result['ticket'], $result['user']);
    }

    private function page(string $state, $ticket = null, $user = null)
    {
        return response()
            ->view('helpdesk_ticket.validate_link', compact('state', 'ticket', 'user'))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
