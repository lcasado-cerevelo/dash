<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workdo\Taskly\Entities\TicketInboundMailbox;
use Workdo\Taskly\Services\TicketInboundEmailService;

class TasklyFetchInboundEmail extends Command
{
    protected $signature = 'taskly:fetch-inbound-email';
    protected $description = 'Procesa correos IMAP y crea tasks en Taskly';

    public function handle(TicketInboundEmailService $service)
    {
        $mailboxes = TicketInboundMailbox::where('is_active', 1)->get();

        foreach ($mailboxes as $mailbox) {
            try {
                $count = $service->processMailbox($mailbox);
                $this->info("Mailbox {$mailbox->name}: {$count} correos procesados.");
            } catch (\Throwable $e) {
                $this->error("Mailbox {$mailbox->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}