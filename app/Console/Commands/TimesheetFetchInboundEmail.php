<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Services\TimesheetEmailService;

class TimesheetFetchInboundEmail extends Command
{
    protected $signature   = 'timesheet:fetch-inbound-email';
    protected $description = 'Procesa buzones IMAP y crea timesheets a partir de correos entrantes';

    public function handle(TimesheetEmailService $service): int
    {
        $mailboxes = TimesheetInboundMailbox::where('is_active', 1)->get();

        if ($mailboxes->isEmpty()) {
            $this->warn('No hay buzones de timesheet activos. Configúralos en Company → Settings.');
            return self::SUCCESS;
        }

        $total = 0;

        foreach ($mailboxes as $mailbox) {
            $this->line("Procesando: <info>{$mailbox->name}</info> ({$mailbox->username})");
            try {
                $count = $service->processMailbox($mailbox);
                $this->info("  → {$count} correo(s) procesado(s).");
                $total += $count;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$e->getMessage()}");
            }
        }

        $this->info("Total timesheets creados: {$total}");

        return self::SUCCESS;
    }
}
