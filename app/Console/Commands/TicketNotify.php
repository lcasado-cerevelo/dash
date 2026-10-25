<?php

namespace App\Console\Commands;

use App\Services\TicketNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TicketNotify extends Command
{
    protected $signature = 'tickets:notify
        {--only= : weekly | friday | reminders. Por defecto: weekly solo los lunes, friday solo los viernes y reminders todos los días}
        {--force : Ignora el candado de "el resumen semanal ya se envió hoy"}
        {--to= : Envía todo únicamente a esta dirección, sin copias (pruebas). No guarda estado}
        {--ticket=* : Limita el envío a este ticket (el número que se ve en pantalla, ej. 1042; repetible). Ignora la regla de 7 días. No guarda estado}
        {--dry-run : Muestra qué enviaría sin enviar nada ni guardar estado}
        {--seed : Marca como "recordado hoy" los tickets que hoy tocaría recordar, sin enviar nada (arranque suave: el primer recordatorio sale en 7 días)}';

    protected $description = 'Envía los correos automáticos de tickets: resumen semanal (lunes) y recordatorios de validación (cada 7 días)';

    private TicketNotificationService $svc;
    private bool $dry;

    public function handle(TicketNotificationService $svc): int
    {
        $this->svc      = $svc;
        $this->dry = (bool) $this->option('dry-run');

        $svc->forceTo($this->option('to') ?: null);
        $svc->onlyTickets((array) $this->option('ticket'));

        if ($svc->testRecipient()) {
            $this->warn('MODO PRUEBA: todos los correos se enviarán solo a ' . $svc->testRecipient() . ' (sin copias).');
        }
        if ($svc->hasTicketFilter()) {
            $this->warn('Limitado a los tickets indicados con --ticket.');
        }

        if ($this->option('seed')) {
            return $this->seed();
        }

        $only = $this->option('only');
        // Cualquier modo de prueba o filtro: no se lee ni se guarda el estado "ya enviado".
        $persist = !$this->dry && !$svc->testRecipient() && !$svc->hasTicketFilter();
        $state   = $svc->loadState();
        $today   = now()->toDateString();

        $runWeekly    = $only === 'weekly' || ($only === null && now()->isMonday());
        $runFriday    = $only === 'friday' || ($only === null && now()->isFriday());
        $runReminders = $only === 'reminders' || $only === null;

        if ($runWeekly) {
            if (!$this->option('force') && $persist && $state['weekly_last'] === $today) {
                $this->info('El resumen semanal ya se envió hoy; se omite (use --force para repetirlo).');
            } else {
                $this->weeklyOpenTickets();
                $this->weeklyPendingValidation();
                if ($persist) {
                    $state['weekly_last'] = $today;
                }
            }
        }

        if ($runFriday) {
            if (!$this->option('force') && $persist && $state['friday_last'] === $today) {
                $this->info('El resumen del viernes ya se envió hoy; se omite (use --force para repetirlo).');
            } elseif ($this->fridaySummary() && $persist) {
                $state['friday_last'] = $today;
            }
        }

        $pending = $svc->pendingValidation();

        if ($runReminders) {
            $this->validationReminders($pending, $state, $persist);
        }

        if ($persist) {
            // Olvida los recordatorios de tickets que ya no están en Validation.
            $live = $pending->map(fn ($i) => $svc->reminderKey($i))->flip();
            $state['reminders'] = array_intersect_key($state['reminders'], $live->all());
            $svc->saveState($state);
        }

        return self::SUCCESS;
    }

    /** Arranque suave: evita que al activar el cron lleguen de golpe recordatorios de todo lo que ya estaba en Validation. */
    private function seed(): int
    {
        $state = $this->svc->loadState();
        $n = 0;

        foreach ($this->svc->pendingValidation() as $item) {
            if ($this->svc->reminderIsDue($item, $state)) {
                $state['reminders'][$this->svc->reminderKey($item)] = now()->toDateString();
                $n++;
            }
        }

        if ($this->dry) {
            $this->line("[dry-run] se marcarían {$n} ticket(s); no se guardó nada.");
            return self::SUCCESS;
        }

        $this->svc->saveState($state);
        $this->info("{$n} ticket(s) marcados como recordados hoy; su primer recordatorio saldrá dentro de " . config('ticket_notify.reminder_days') . ' días.');

        return self::SUCCESS;
    }

    /** Correo 4 (viernes): tickets creados, enviados a validar y cerrados del viernes anterior al jueves. */
    private function fridaySummary(): bool
    {
        $recipients = config('ticket_notify.friday_to');
        if (!$recipients && $this->svc->testRecipient()) {
            $recipients = [$this->svc->testRecipient()];
        }
        if (!$recipients) {
            $this->warn('Resumen del viernes omitido: defina TICKET_NOTIFY_FRIDAY_TO en el .env.');
            return false;
        }

        $week = $this->svc->weekSummary();

        $html = view('email.ticket_week_summary', ['week' => $week, 'svc' => $this->svc])->render();
        $subject = 'Resumen semanal de tickets (' . $week['start']->format('d/m') . ' al ' . $week['end']->format('d/m') . ')';

        $ok = false;
        foreach ($recipients as $to) {
            $ok = $this->deliver('Correo 4', $to, [], $subject, $html, null) || $ok;
        }

        return $ok;
    }

    /** Correo 1: tickets abiertos a quien los tiene asignado, con copia a TICKET_NOTIFY_CC. */
    private function weeklyOpenTickets(): void
    {
        foreach ($this->svc->openTicketsByAssignee() as $group) {
            $html = view('email.ticket_open_digest', [
                'user'    => $group['user'],
                'tickets' => $group['tickets'],
                'svc'     => $this->svc,
            ])->render();

            $this->deliver(
                'Correo 1',
                $group['user']->email,
                config('ticket_notify.cc'),
                'Tickets abiertos asignados a ' . $group['user']->name . ' (' . $group['tickets']->count() . ')',
                $html,
                $group['workspace']
            );
        }
    }

    /** Correo 2: a quien pasó los tickets a Validation, con copia a TICKET_NOTIFY_CC. */
    private function weeklyPendingValidation(): void
    {
        $groups = $this->svc->pendingValidation()
            ->filter(fn ($i) => $i['resolver'] && !empty($i['resolver']->email))
            ->groupBy(fn ($i) => $i['resolver']->id . '|' . ((int) $i['ticket']->workspace === 5 ? 5 : 0));

        foreach ($groups as $items) {
            $resolver = $items->first()['resolver'];

            $html = view('email.ticket_validation_pending', [
                'user'  => $resolver,
                'items' => $items,
                'svc'   => $this->svc,
            ])->render();

            $this->deliver(
                'Correo 2',
                $resolver->email,
                config('ticket_notify.cc'),
                'Tickets que enviaste a validar y siguen pendientes (' . $items->count() . ')',
                $html,
                (int) $items->first()['ticket']->workspace === 5 ? 5 : null
            );
        }
    }

    /** Correo 3: recordatorio con botón al asignado, solo a él (sin copias porque el botón funciona sin login). */
    private function validationReminders($pending, array &$state, bool $persist): void
    {
        foreach ($pending as $item) {
            $due = $this->svc->hasTicketFilter()
                ? ($item['assignee'] && !empty($item['assignee']->email))
                : $this->svc->reminderIsDue($item, $state);

            if (!$due) {
                continue;
            }

            $html = view('email.ticket_validation_reminder', [
                'item'        => $item,
                'replies'     => $this->svc->lastReplies($item['ticket']),
                'validateUrl' => $this->svc->validationUrl($item),
                'svc'         => $this->svc,
            ])->render();

            $ok = $this->deliver(
                'Correo 3',
                $item['assignee']->email,
                [],
                'Validar ticket #' . $item['ticket']->ticket_id . ': ' . Str::limit($item['ticket']->subject, 80),
                $html,
                (int) $item['ticket']->workspace === 5 ? 5 : null
            );

            if ($ok && $persist) {
                $state['reminders'][$this->svc->reminderKey($item)] = now()->toDateString();
            }
        }
    }

    private function deliver(string $label, string $to, array $cc, string $subject, string $html, ?int $workspace): bool
    {
        $copy = $cc ? ' (cc: ' . implode(', ', $cc) . ')' : '';
        if ($this->svc->testRecipient()) {
            $copy .= ' [redirigido a ' . $this->svc->testRecipient() . ']';
        }

        if ($this->dry) {
            $this->line("[dry-run] {$label} -> {$to}{$copy} :: {$subject}");
            return false;
        }

        $result = $this->svc->send([$to], $cc, $subject, $html, $workspace);

        if ($result['is_success']) {
            $this->info("{$label} enviado -> {$to}{$copy} :: {$subject}");
            return true;
        }

        $this->error("{$label} FALLÓ -> {$to}: " . $result['error']);
        return false;
    }
}
