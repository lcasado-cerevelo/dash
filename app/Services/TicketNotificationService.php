<?php

namespace App\Services;

use App\Models\HelpdeskConversion;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAudit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Notificaciones automáticas de tickets.
 *
 * No usa tablas ni columnas nuevas: el "quién" y el "desde cuándo" de un ticket en Validation
 * salen de helpdesk_ticket_audits (última fila status -> Validation), y lo ya enviado se
 * recuerda en un archivo JSON dentro de storage/app.
 */
class TicketNotificationService
{
    /** Si está definido, TODO envío se redirige a esta dirección (modo prueba). */
    private ?string $testTo = null;

    /** Si no está vacío, las consultas se limitan a estos números de ticket (columna ticket_id, la que se ve en pantalla). */
    private array $onlyTickets = [];

    public function __construct()
    {
        $this->testTo = config('ticket_notify.test_to') ?: null;
    }

    public function forceTo(?string $address): void
    {
        if ($address) {
            $this->testTo = $address;
        }
    }

    public function testRecipient(): ?string
    {
        return $this->testTo;
    }

    public function onlyTickets(array $ids): void
    {
        $this->onlyTickets = array_values(array_filter(array_map(fn ($i) => trim(ltrim(trim((string) $i), '#')), $ids), 'strlen'));
    }

    public function hasTicketFilter(): bool
    {
        return !empty($this->onlyTickets);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Correo 1: tickets no cerrados agrupados por asignado.
     *
     * @return Collection<int, array{user: User, workspace: ?int, tickets: Collection}>
     */
    public function openTicketsByAssignee(): Collection
    {
        $tickets = HelpdeskTicket::query()
            ->whereNotNull('user_id')
            ->whereNotIn('status', config('ticket_notify.closed_statuses'))
            ->when($this->onlyTickets, fn ($q) => $q->whereIn('ticket_id', $this->onlyTickets))
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        $users = User::whereIn('id', $tickets->pluck('user_id')->unique())->get()->keyBy('id');

        return $tickets
            ->groupBy(fn ($t) => $t->user_id . '|' . $this->mailWorkspace($t))
            ->map(function (Collection $group) use ($users) {
                $first = $group->first();
                return [
                    'user'      => $users->get($first->user_id),
                    'workspace' => $this->mailWorkspace($first),
                    'tickets'   => $group,
                ];
            })
            ->filter(fn ($g) => $g['user'] && !empty($g['user']->email))
            ->values();
    }

    /**
     * Tickets hoy en Validation, con quién los pasó a Validation y desde cuándo.
     *
     * @return Collection<int, array{ticket: HelpdeskTicket, audit_id: int, since: Carbon, days: int, resolver: ?User, assignee: ?User}>
     */
    public function pendingValidation(): Collection
    {
        $tickets = HelpdeskTicket::query()
            ->where('status', config('ticket_notify.validation_status'))
            ->when($this->onlyTickets, fn ($q) => $q->whereIn('ticket_id', $this->onlyTickets))
            ->orderBy('created_at')
            ->get();

        if ($tickets->isEmpty()) {
            return collect();
        }

        // Última fila de auditoría "status -> Validation" por ticket.
        $audits = HelpdeskTicketAudit::query()
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->where('field_name', 'status')
            ->where('new_value', config('ticket_notify.validation_status'))
            ->orderBy('id')
            ->get()
            ->keyBy('ticket_id'); // al ir en orden ascendente, queda la más reciente

        $userIds = $tickets->pluck('user_id')
            ->merge($audits->pluck('changed_by'))
            ->filter()
            ->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $tickets->map(function (HelpdeskTicket $ticket) use ($audits, $users) {
            $audit    = $audits->get($ticket->id);
            $assignee = $ticket->user_id ? $users->get($ticket->user_id) : null;
            // Sin autor identificable: se avisa al asignado.
            $resolver = ($audit && $audit->changed_by) ? $users->get($audit->changed_by) : null;
            $since    = $audit ? $audit->created_at : $ticket->created_at;

            return [
                'ticket'   => $ticket,
                'audit_id' => $audit ? (int) $audit->id : 0,
                'since'    => $since,
                'days'     => $this->daysBetween($since, now()),
                'resolver' => $resolver ?: $assignee,
                'assignee' => $assignee,
            ];
        })->values();
    }

    /**
     * Resumen de la semana para el correo del viernes: del viernes anterior (hace 7 días) al jueves (ayer).
     * Creados, enviados a Validation y cerrados en ese rango, más el total de abiertos hoy.
     */
    public function weekSummary(): array
    {
        $start = now()->subDays(7)->startOfDay();
        $end   = now()->subDay()->endOfDay();

        $created = HelpdeskTicket::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($this->onlyTickets, fn ($q) => $q->whereIn('ticket_id', $this->onlyTickets))
            ->orderBy('created_at')
            ->get();

        // Último cambio de estado a $status dentro del rango, por ticket.
        $moved = function (string $status) use ($start, $end) {
            $audits = HelpdeskTicketAudit::query()
                ->where('field_name', 'status')
                ->where('new_value', $status)
                ->whereBetween('created_at', [$start, $end])
                ->orderBy('id')
                ->get()
                ->keyBy('ticket_id');

            $tickets = HelpdeskTicket::query()
                ->whereIn('id', $audits->keys())
                ->when($this->onlyTickets, fn ($q) => $q->whereIn('ticket_id', $this->onlyTickets))
                ->get()
                ->keyBy('id');

            return $audits
                ->filter(fn ($a) => $tickets->has($a->ticket_id))
                ->map(fn ($a) => ['ticket' => $tickets[$a->ticket_id], 'when' => $a->created_at, 'by' => (int) $a->changed_by])
                ->values();
        };

        $validation = $moved(config('ticket_notify.validation_status'));
        $closed     = $moved(config('ticket_notify.validated_status'));

        $userIds = $created->pluck('user_id')
            ->merge($validation->pluck('ticket.user_id'))
            ->merge($validation->pluck('by'))
            ->merge($closed->pluck('ticket.user_id'))
            ->merge($closed->pluck('by'))
            ->filter()
            ->unique();

        return [
            'start'      => $start,
            'end'        => $end,
            'created'    => $created,
            'validation' => $validation,
            'closed'     => $closed,
            'open_now'   => HelpdeskTicket::whereNotIn('status', config('ticket_notify.closed_statuses'))->count(),
            'users'      => User::whereIn('id', $userIds)->get()->keyBy('id'),
        ];
    }

    /** Id de la auditoría con la que el ticket entró a Validation por última vez (0 si no hay). */
    public function currentEpisode(int $ticketId): int
    {
        return (int) HelpdeskTicketAudit::query()
            ->where('ticket_id', $ticketId)
            ->where('field_name', 'status')
            ->where('new_value', config('ticket_notify.validation_status'))
            ->max('id');
    }

    // ------------------------------------------------------------------
    // Recordatorios (correo 3): día 7, 14, 21...
    // ------------------------------------------------------------------

    public function reminderIsDue(array $item, array $state): bool
    {
        $every = max(1, (int) config('ticket_notify.reminder_days'));

        if ($item['days'] < $every || !$item['assignee'] || empty($item['assignee']->email)) {
            return false;
        }

        $last = $state['reminders'][$this->reminderKey($item)] ?? null;
        if (!$last) {
            return true;
        }

        return $this->daysBetween(Carbon::parse($last), now()) >= $every;
    }

    public function reminderKey(array $item): string
    {
        return $item['ticket']->id . ':' . $item['audit_id'];
    }

    /** URL firmada de un solo uso (por estado) y con vencimiento, para validar desde el correo. */
    public function validationUrl(array $item): string
    {
        $relative = URL::temporarySignedRoute(
            'helpdesk.validate.show',
            now()->addDays((int) config('ticket_notify.link_days')),
            [
                'ticket' => $item['ticket']->id,
                'audit'  => $item['audit_id'],
                'user'   => $item['assignee']->id,
            ],
            false
        );

        return rtrim((string) config('app.url'), '/') . $relative;
    }

    public function ticketUrl(HelpdeskTicket $ticket): string
    {
        return route('helpdesk.view', [Crypt::encrypt($ticket->ticket_id)]);
    }

    /** Últimos comentarios del ticket para dar contexto en el correo. */
    public function lastReplies(HelpdeskTicket $ticket, int $limit = 2): Collection
    {
        $replies = HelpdeskConversion::query()
            ->where('ticket_id', $ticket->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $users = User::whereIn('id', $replies->pluck('sender')->filter(fn ($s) => ctype_digit((string) $s)))
            ->get()->keyBy('id');

        return $replies->reverse()->values()->map(fn ($r) => [
            'who'  => $users->get((int) $r->sender)->name ?? __('Sistema'),
            'when' => $r->created_at,
            'text' => $this->plain($r->description, 400),
        ]);
    }

    public function plain(?string $html, int $limit = 600): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return Str::limit(trim(preg_replace('/\s+/', ' ', $text)), $limit);
    }

    // ------------------------------------------------------------------
    // Validar desde el enlace
    // ------------------------------------------------------------------

    /**
     * Verifica que el enlace siga siendo válido según el estado actual del ticket.
     * reason: not_found | already | superseded | reassigned
     */
    public function checkLink(int $ticketId, int $auditId, int $userId, bool $lock = false): array
    {
        $query = HelpdeskTicket::query();
        if ($lock) {
            $query->lockForUpdate();
        }

        $ticket = $query->find($ticketId);
        $user   = User::find($userId);

        if (!$ticket || !$user) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ($ticket->status !== config('ticket_notify.validation_status')) {
            return ['ok' => false, 'reason' => 'already', 'ticket' => $ticket];
        }
        if ($this->currentEpisode($ticket->id) !== $auditId) {
            return ['ok' => false, 'reason' => 'superseded', 'ticket' => $ticket];
        }
        if ((int) $ticket->user_id !== $userId) {
            return ['ok' => false, 'reason' => 'reassigned', 'ticket' => $ticket];
        }

        return ['ok' => true, 'ticket' => $ticket, 'user' => $user];
    }

    /** Valida (cierra) el ticket igual que lo hace changeStatus, atribuido al asignado. */
    public function validateTicket(int $ticketId, int $auditId, int $userId): array
    {
        return DB::transaction(function () use ($ticketId, $auditId, $userId) {
            $check = $this->checkLink($ticketId, $auditId, $userId, true);
            if (!$check['ok']) {
                return $check;
            }

            /** @var HelpdeskTicket $ticket */
            $ticket = $check['ticket'];
            $user   = $check['user'];
            $old    = $ticket->status;

            $ticket->status = config('ticket_notify.validated_status');
            $ticket->save();

            HelpdeskTicketService::audit($ticket, 'status', $old, $ticket->status, $user->id);
            HelpdeskTicketService::createSystemReply(
                $ticket,
                'El ticket fue validado y cerrado por ' . $user->name . ' (desde el correo de recordatorio).',
                $user->id
            );

            return $check + ['done' => true];
        });
    }

    // ------------------------------------------------------------------
    // Estado (archivo JSON, sin base de datos)
    // ------------------------------------------------------------------

    public function loadState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return ['weekly_last' => null, 'friday_last' => null, 'reminders' => []];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return [
            'weekly_last' => $data['weekly_last'] ?? null,
            'friday_last' => $data['friday_last'] ?? null,
            'reminders'   => is_array($data['reminders'] ?? null) ? $data['reminders'] : [],
        ];
    }

    public function saveState(array $state): void
    {
        file_put_contents(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function statePath(): string
    {
        return storage_path('app/' . config('ticket_notify.state_file'));
    }

    // ------------------------------------------------------------------
    // Envío (Brevo o SendGrid por API, con copia)
    // ------------------------------------------------------------------

    /**
     * @param string[] $to
     * @param string[] $cc
     */
    public function send(array $to, array $cc, string $subject, string $html, ?int $workspace = null): array
    {
        $to = $this->cleanEmails($to);
        $cc = array_values(array_diff($this->cleanEmails($cc), $to));

        if ($this->testTo) {
            $banner = '<div style="background:#fff3cd;border:1px solid #ffe69c;padding:10px;margin-bottom:16px;font:13px Arial,sans-serif;">'
                . '<strong>MODO PRUEBA</strong> — destinatarios reales: Para: ' . e(implode(', ', $to))
                . ($cc ? ' · Cc: ' . e(implode(', ', $cc)) : '') . '</div>';
            $html    = $banner . $html;
            $subject = '[PRUEBA] ' . $subject;
            $to      = $this->cleanEmails([$this->testTo]);
            $cc      = [];
        }

        if (empty($to)) {
            return ['is_success' => false, 'error' => 'Sin destinatario válido'];
        }

        $driver = config('ticket_notify.driver');

        $result = $driver === 'sendgrid'
            ? $this->sendViaSendGrid($to, $cc, $subject, $html, $workspace)
            : $this->sendViaBrevo($to, $cc, $subject, $html);

        Log::info('ticket-notify mail', [
            'driver' => $driver, 'to' => $to, 'cc' => $cc, 'subject' => $subject,
            'ok' => $result['is_success'], 'error' => $result['error'],
        ]);

        return $result;
    }

    private function sendViaBrevo(array $to, array $cc, string $subject, string $html): array
    {
        $cfg = config('ticket_notify.brevo');

        if (empty($cfg['api_key']) || empty($cfg['from_address'])) {
            return ['is_success' => false, 'error' => 'Falta configuración de Brevo en .env (BREVO_API_KEY y BREVO_FROM_ADDRESS)'];
        }

        $payload = [
            'sender'      => ['email' => $cfg['from_address'], 'name' => (string) $cfg['from_name']],
            'to'          => array_map(fn ($e) => ['email' => $e], $to),
            'subject'     => $subject,
            'htmlContent' => $html,
        ];
        if ($cc) {
            $payload['cc'] = array_map(fn ($e) => ['email' => $e], $cc);
        }

        return $this->postJson('https://api.brevo.com/v3/smtp/email', [
            'api-key: ' . $cfg['api_key'],
            'accept: application/json',
            'Content-Type: application/json',
        ], $payload, 'Brevo');
    }

    private function sendViaSendGrid(array $to, array $cc, string $subject, string $html, ?int $workspace): array
    {
        $cfg = config('ticket_notify.sendgrid.' . ((int) $workspace === 5 ? 'innova' : 'default'));

        if (empty($cfg['api_key']) || empty($cfg['from_address'])) {
            return ['is_success' => false, 'error' => 'Falta configuración de SendGrid en .env'];
        }

        $personalization = ['to' => array_map(fn ($e) => ['email' => $e], $to)];
        if ($cc) {
            $personalization['cc'] = array_map(fn ($e) => ['email' => $e], $cc);
        }

        return $this->postJson('https://api.sendgrid.com/v3/mail/send', [
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ], [
            'personalizations' => [$personalization],
            'from'    => ['email' => $cfg['from_address'], 'name' => (string) $cfg['from_name']],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $html]],
        ], 'SendGrid');
    }

    private function postJson(string $url, array $headers, array $payload, string $label): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['is_success' => false, 'error' => $curlError];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['is_success' => true, 'error' => false];
        }

        return ['is_success' => false, 'error' => $label . ' HTTP ' . $httpCode . ': ' . $response];
    }

    // ------------------------------------------------------------------

    private function cleanEmails(array $emails): array
    {
        return collect($emails)
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function mailWorkspace(HelpdeskTicket $ticket): ?int
    {
        return ((int) $ticket->workspace === 5) ? 5 : null;
    }

    private function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) floor($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay(), false));
    }
}
