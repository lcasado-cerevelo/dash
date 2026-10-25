<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Entities\TimesheetInboundSenderMap;
use Carbon\Carbon;

/**
 * Comando de diagnóstico — NO crea timesheets, NO marca emails como procesados.
 * Solo lee, analiza y muestra en pantalla qué haría el servicio real.
 *
 * Uso:
 *   php artisan timesheet:debug-email
 *   php artisan timesheet:debug-email --mailbox=1
 *   php artisan timesheet:debug-email --mailbox=1 --limit=5
 */
class TimesheetDebugEmail extends Command
{
    protected $signature = 'timesheet:debug-email
                            {--mailbox= : ID del mailbox a revisar (si no se indica, usa el primero activo)}
                            {--limit=3  : Cuántos emails revisar (por defecto 3)}';

    protected $description = 'Diagnostica por qué los emails no generan timesheets (modo solo-lectura, sin crear nada)';

    public function handle(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║     TIMESHEET EMAIL — DIAGNÓSTICO (solo lectura)     ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->line('');

        // ── 1. Verificar extensión IMAP ───────────────────────────────────────
        $this->line('<options=bold>PASO 1: Extensión PHP IMAP</>');
        if (!extension_loaded('imap')) {
            $this->error('  ✗ La extensión php-imap NO está cargada.');
            $this->warn('    En GoDaddy: verifica en cPanel → PHP Selector → Extensions → imap');
            return self::FAILURE;
        }
        $this->info('  ✓ php-imap cargado');
        $this->line('');

        // ── 2. Mailboxes configurados ─────────────────────────────────────────
        $this->line('<options=bold>PASO 2: Mailboxes en base de datos</>');
        $mailboxes = TimesheetInboundMailbox::all();

        if ($mailboxes->isEmpty()) {
            $this->error('  ✗ No hay mailboxes configurados en timesheet_inbound_mailboxes.');
            $this->warn('    Agrégalos en Company → Settings → Timesheet Inbound Mailboxes');
            return self::FAILURE;
        }

        foreach ($mailboxes as $mb) {
            $status = $mb->is_active ? '<fg=green>ACTIVO</>' : '<fg=red>INACTIVO</>';
            $this->line("  [{$mb->id}] {$mb->name} | {$mb->username}@{$mb->imap_host}:{$mb->imap_port} | {$status}");
        }
        $this->line('');

        // Seleccionar mailbox a usar
        $mailboxId = $this->option('mailbox');
        $mailbox   = $mailboxId
            ? TimesheetInboundMailbox::find($mailboxId)
            : TimesheetInboundMailbox::where('is_active', 1)->first();

        if (!$mailbox) {
            $this->error('  ✗ Mailbox no encontrado. Usa --mailbox=ID');
            return self::FAILURE;
        }

        $this->line("  Usando mailbox: <info>[{$mailbox->id}] {$mailbox->name}</info>");
        $this->line('');

        // ── 3. Sender maps configurados ───────────────────────────────────────
        $this->line('<options=bold>PASO 3: Sender Maps en base de datos</>');
        $senderMaps = TimesheetInboundSenderMap::where('is_active', 1)->orderBy('sort_order')->get();

        if ($senderMaps->isEmpty()) {
            $this->warn('  ⚠ No hay sender maps activos en timesheet_inbound_sender_maps.');
            $this->warn('    Sin sender maps, NINGÚN email generará timesheets.');
            $this->warn('    Agrégalos en Company → Settings → Timesheet Sender Maps');
        } else {
            foreach ($senderMaps as $sm) {
                $mbLabel = $sm->mailbox_id ? "mailbox #{$sm->mailbox_id}" : 'todos los mailboxes';
                $emp     = $sm->employee_user_id
                    ? DB::table('users')->where('id', $sm->employee_user_id)->value('email')
                    : '(resolver por From)';
                $this->line("  [{$sm->id}] {$sm->match_type}:{$sm->match_value} | empleado:{$emp} | {$mbLabel}");
            }
        }
        $this->line('');

        // ── 4. Conexión IMAP ──────────────────────────────────────────────────
        $this->line('<options=bold>PASO 4: Conexión IMAP</>');
        try {
            $password   = Crypt::decryptString($mailbox->password_enc);
            $flags      = $mailbox->imap_ssl ? '/imap/ssl' : '/imap/notls';
            $mboxString = sprintf(
                '{%s:%d%s}%s',
                $mailbox->imap_host,
                $mailbox->imap_port,
                $flags,
                $mailbox->folder ?: 'INBOX'
            );

            $this->line("  Conectando a: {$mboxString}");
            $inbox = @imap_open($mboxString, $mailbox->username, $password);

            if (!$inbox) {
                $this->error('  ✗ Conexión IMAP fallida: ' . imap_last_error());
                $this->warn('    Verifica host, puerto, usuario y contraseña.');
                $this->warn('    En GoDaddy shared hosting, el host IMAP suele ser diferente al dominio.');
                $this->warn('    Ej: mail.tudominio.com  en vez de  imap.tudominio.com');
                return self::FAILURE;
            }

            $this->info('  ✓ Conexión exitosa');
        } catch (\Throwable $e) {
            $this->error('  ✗ Error al conectar: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── 5. Emails no leídos ───────────────────────────────────────────────
        $this->line('');
        $this->line('<options=bold>PASO 5: Emails UNSEEN en el buzón</>');
        $emails = imap_search($inbox, 'UNSEEN');

        if (!$emails) {
            $this->warn('  ⚠ No hay emails sin leer (UNSEEN) en el buzón.');
            $this->warn('    Envía un correo de prueba al buzón y espera unos segundos.');

            // También revisar los recientes por si ya fueron marcados como leídos
            $this->line('');
            $this->line('  Revisando los últimos 5 emails (ALL) para referencia:');
            $all = imap_search($inbox, 'ALL');
            if ($all) {
                $recent = array_slice(array_reverse($all), 0, 5);
                foreach ($recent as $no) {
                    $h       = imap_headerinfo($inbox, $no);
                    $subject = isset($h->subject) ? imap_utf8($h->subject) : '(sin asunto)';
                    $from    = $h->from[0]->mailbox . '@' . $h->from[0]->host;
                    $flags   = imap_fetchheader($inbox, $no);
                    $seen    = strpos($flags, '\Seen') !== false ? 'LEÍDO' : 'NO LEÍDO';
                    $this->line("    [{$no}] {$seen} | From: {$from} | Subject: {$subject}");
                }
            }

            imap_close($inbox);
            return self::SUCCESS;
        }

        $this->info('  ✓ ' . count($emails) . ' email(s) UNSEEN encontrado(s)');
        $this->line('');

        // ── 6. Analizar cada email ────────────────────────────────────────────
        $limit  = (int) ($this->option('limit') ?? 3);
        $toScan = array_slice(array_reverse($emails), 0, $limit);

        foreach ($toScan as $emailNo) {
            $this->line('  ─────────────────────────────────────────────────────');
            $this->line("  <options=bold>Email #{$emailNo}</>");

            $rawHeader = imap_fetchheader($inbox, $emailNo);
            $header    = imap_headerinfo($inbox, $emailNo);

            // Message-ID
            $messageId = null;
            if (preg_match('/^Message-ID:\s*(.+)$/mi', $rawHeader, $m)) {
                $messageId = trim($m[1]);
            }
            $messageId = $messageId ?: ('ts-imap-' . $mailbox->id . '-' . imap_uid($inbox, $emailNo));
            $this->line("    Message-ID : {$messageId}");

            // Ya procesado?
            $alreadyDone = DB::table('timesheet_inbound_processed')
                ->where('mailbox_id', $mailbox->id)
                ->where('message_id', $messageId)
                ->first();

            if ($alreadyDone) {
                $this->warn("    ⚠ Ya procesado anteriormente (status: {$alreadyDone->status}, timesheets: {$alreadyDone->timesheets_created})");
                $this->warn("      Si quieres reprocesarlo: DELETE FROM timesheet_inbound_processed WHERE message_id = '{$messageId}';");
                continue;
            }
            $this->info('    ✓ No está en processed (es nuevo)');

            // From
            $from = $header->from[0] ?? null;
            $fromEmail = $from ? strtolower($from->mailbox . '@' . $from->host) : null;
            $this->line("    From       : {$fromEmail}");

            // Subject
            $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
            $this->line("    Subject    : {$subject}");

            // Resolver sender map
            $this->line('');
            $this->line('    <options=bold>[A] Resolución de Sender Map</>');
            $map = $this->resolveSenderMap($mailbox->id, $fromEmail, $senderMaps);

            if ($map === null) {
                $this->error("    ✗ PROBLEMA: No hay sender map para '{$fromEmail}'");
                $this->warn("      Agrega un map en Company → Settings con:");
                $this->warn("        match_type = email");
                $this->warn("        match_value = {$fromEmail}");
                $this->warn("      O para todo el dominio:");
                $domainPart = strstr((string)$fromEmail, '@');
                $this->warn("        match_type = domain");
                $this->warn("        match_value = {$domainPart}");
                continue;
            }

            $this->info("    ✓ Sender map encontrado");
            $this->line("      employee_user_id   : " . ($map['employee_user_id']   ?? 'null (resolver por From)'));
            $this->line("      project_id         : " . ($map['project_id']         ?? 'null'));
            $this->line("      task_id            : " . ($map['task_id']            ?? 'null'));
            $this->line("      customer_id        : " . ($map['customer_id']        ?? 'null'));
            $this->line("      customer_client_id : " . ($map['customer_client_id'] ?? 'null'));

            // Resolver empleado
            $this->line('');
            $this->line('    <options=bold>[B] Resolución de Empleado</>');
            $employeeUserId = $map['employee_user_id'];

            if (!$employeeUserId && $fromEmail) {
                $employeeUserId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [$fromEmail])
                    ->value('id');

                if ($employeeUserId) {
                    $this->info("    ✓ Empleado resuelto por From email: user_id={$employeeUserId}");
                } else {
                    $this->error("    ✗ PROBLEMA: El email '{$fromEmail}' no existe en la tabla users.");
                    $this->warn("      Opciones:");
                    $this->warn("      1. Asigna el employee_user_id directamente en el sender map.");
                    $this->warn("      2. Crea un usuario con ese email.");
                    $users = DB::table('users')->select('id', 'name', 'email')->limit(5)->get();
                    $this->line('      Primeros usuarios en BD:');
                    foreach ($users as $u) {
                        $this->line("        [{$u->id}] {$u->name} — {$u->email}");
                    }
                    continue;
                }
            } else {
                $empEmail = DB::table('users')->where('id', $employeeUserId)->value('email');
                $this->info("    ✓ Empleado desde sender map: user_id={$employeeUserId} ({$empEmail})");
            }

            // Parsear asunto
            $this->line('');
            $this->line('    <options=bold>[C] Parseo de fecha/hora</>');
            
            $structure = imap_fetchstructure($inbox, $emailNo);
            $body      = $this->getBodySnippet($inbox, $emailNo, $structure);
            
            $range = $this->parseDateTimeRange($subject);
            
            if (!$range) {
                $range = $this->parseDateTimeRangeFromBody($body);
            }
            
            if (!$range) {
                $this->error("    ✗ PROBLEMA: No se pudo parsear el rango ni del asunto ni del body.");
                $this->warn("      Asunto recibido: \"{$subject}\"");
                $this->warn("      Body recibido: \"" . mb_substr(trim($body), 0, 300) . "\"");
                continue;
            }

            [$fromDt, $toDt] = $range;
            $this->info("    ✓ Rango parseado:");
            $this->line("      From : {$fromDt->toDateTimeString()}");
            $this->line("      To   : {$toDt->toDateTimeString()}");

            // Calcular split de días
            $this->line('');
            $this->line('    <options=bold>[D] Split multi-día (timesheets a crear)</>');
            $fromDay = $fromDt->copy()->startOfDay();
            $toDay   = $toDt->copy()->startOfDay();
            $cursor  = $fromDay->copy();
            $dayCount = 0;

            while ($cursor->lte($toDay)) {
                $dayFrom = $cursor->eq($fromDay) ? $fromDt->copy() : $cursor->copy()->setTime(0, 0);
                $dayTo   = $cursor->eq($toDay)   ? $toDt->copy()   : $cursor->copy()->setTime(23, 59);
                $mins    = (int) $dayFrom->diffInMinutes($dayTo);
                $h       = intdiv($mins, 60);
                $m       = $mins % 60;

                if ($mins > 0) {
                    $dayCount++;
                    $this->info("    ✓ Día {$dayCount}: {$cursor->toDateString()} | {$dayFrom->format('H:i')} - {$dayTo->format('H:i')} | {$h}h {$m}m");
                }
                $cursor->addDay();
            }

            if ($dayCount === 0) {
                $this->error('    ✗ PROBLEMA: El rango calculado da 0 minutos. Revisa las horas.');
            }

            // Cuerpo del mensaje
            $this->line('');
            $this->line('    <options=bold>[E] Cuerpo del email (descripción)</>');
            $structure = imap_fetchstructure($inbox, $emailNo);
            $body      = $this->getBodySnippet($inbox, $emailNo, $structure);
            $snippet   = mb_substr(strip_tags($body), 0, 120);
            $this->line("    Primeros 120 chars: " . ($snippet ?: '(vacío — se usará el asunto)'));

            // Resumen final
            $this->line('');
            if ($dayCount > 0) {
                $this->info("    ✅ RESULTADO: Este email DEBERÍA crear {$dayCount} timesheet(s).");
                $this->warn("    Si el servicio real no los crea, revisa el log de Laravel:");
                $this->warn("      tail -f storage/logs/laravel.log | grep TimesheetEmailService");
            } else {
                $this->error("    ❌ RESULTADO: Este email NO creará timesheets (0 minutos en el rango).");
            }
        }

        imap_close($inbox);

        $this->line('');
        $this->line('══════════════════════════════════════════════════════');
        $this->line('Diagnóstico completado. No se creó ni modificó nada.');
        $this->line('Para ejecutar el servicio real:');
        $this->line('  php artisan timesheet:fetch-inbound-email');
        $this->line('');

        return self::SUCCESS;
    }

    // ── Helpers (replicados del servicio para no depender de él) ─────────────

    private function resolveSenderMap(int $mailboxId, ?string $fromEmail, $maps): ?array
    {
        if (!$fromEmail) {
            return null;
        }

        // Match exacto de email
        foreach ($maps as $map) {
            if ($map->match_type === 'email' && strtolower($map->match_value) === $fromEmail) {
                return $this->mapToArray($map);
            }
        }

        // Match de dominio
        $domain = strstr($fromEmail, '@');
        if ($domain) {
            foreach ($maps as $map) {
                if ($map->match_type === 'domain' && strtolower($map->match_value) === $domain) {
                    return $this->mapToArray($map);
                }
            }
        }

        return null;
    }

    private function mapToArray($map): array
    {
        return [
            'employee_user_id'   => $map->employee_user_id   ? (int) $map->employee_user_id   : null,
            'project_id'         => $map->project_id         ? (int) $map->project_id         : null,
            'task_id'            => $map->task_id            ? (int) $map->task_id            : null,
            'customer_id'        => $map->customer_id        ? (int) $map->customer_id        : null,
            'customer_client_id' => $map->customer_client_id ? (int) $map->customer_client_id : null,
            'workspace_id'       => $map->workspace_id       ? (int) $map->workspace_id       : null,
        ];
    }

    private function parseDateTimeRange(string $subject): ?array
    {
        $s = trim($subject);

        // Patrón 1: dos fechas completas
        if (preg_match(
            '/(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})\s*(?:to|-{1,3})\s*(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/i',
            $s, $m
        )) {
            $from = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[2]);
            $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[4]);
            if ($from && $to && $to->gt($from)) {
                return [$from, $to];
            }
        }

        // Patrón 2: fecha + rango corto
        if (preg_match('/(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/', $s, $m)) {
            $from = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[2]);
            $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[3]);
            if ($from && $to) {
                if ($to->lte($from)) $to->addDay();
                return [$from, $to];
            }
        }

        // Patrón 3: horas primero, fecha después
        if (preg_match('/(\d{2}:\d{2})\s*(?:to|-)\s*(\d{2}:\d{2})\s*(?:on\s+)?(\d{4}-\d{2}-\d{2})/i', $s, $m)) {
            $from = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[1]);
            $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[2]);
            if ($from && $to) {
                if ($to->lte($from)) $to->addDay();
                return [$from, $to];
            }
        }

        return null;
    }

    private function getBodySnippet($inbox, int $emailNo, $structure): string
    {
        $plain = $this->findPart($inbox, $emailNo, $structure, 'PLAIN');
        if ($plain) return $plain;
        $html = $this->findPart($inbox, $emailNo, $structure, 'HTML');
        if ($html) return $html;
        return '';
    }

    private function findPart($inbox, int $emailNo, $structure, string $wantSubtype, string $partNumber = '')
    {
        $wantSubtype = strtoupper($wantSubtype);
        if (($structure->type ?? null) === 0 && strtoupper($structure->subtype ?? '') === $wantSubtype) {
            $section = $partNumber !== '' ? $partNumber : '1';
            $data = imap_fetchbody($inbox, $emailNo, $section, FT_PEEK);
            if (!$data) $data = imap_body($inbox, $emailNo, FT_PEEK);
            return $this->decodePart($data, (int)($structure->encoding ?? 0));
        }
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $i => $part) {
                $num    = $partNumber === '' ? (string)($i + 1) : $partNumber . '.' . ($i + 1);
                $result = $this->findPart($inbox, $emailNo, $part, $wantSubtype, $num);
                if ($result && trim($result) !== '') return $result;
            }
        }
        return '';
    }

    private function decodePart(string $data, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($data),
            4 => quoted_printable_decode($data),
            default => $data,
        };
    }
    
        /** @return array{0: Carbon, 1: Carbon}|null */
    private function parseDateTimeRangeFromBody(string $body): ?array
    {
        $s = trim(str_replace(["\r\n", "\r"], "\n", $body));
    
        $fromText = null;
        $toText   = null;
    
        if (preg_match('/(?:^|\n)\s*(Inicio|Start)\s*:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+[AP]M)\s*$/imu', $s, $m)) {
            $fromText = trim($m[2]);
        }
    
        if (preg_match('/(?:^|\n)\s*(Fin|End)\s*:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+[AP]M)\s*$/imu', $s, $m)) {
            $toText = trim($m[2]);
        }
    
        if (!$fromText || !$toText) {
            return null;
        }
    
        try {
            $from = Carbon::createFromFormat('Y-m-d h:i A', $fromText);
            $to   = Carbon::createFromFormat('Y-m-d h:i A', $toText);
    
            if (!$from || !$to) {
                return null;
            }
    
            if ($to->lte($from)) {
                $to->addDay();
            }
    
            return [$from, $to];
        } catch (\Throwable $e) {
            return null;
        }
    }

}
