<?php

namespace Workdo\Timesheet\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Timesheet\Entities\TimesheetInboundMailbox;
use Workdo\Timesheet\Entities\TimesheetInboundSenderMap;
use Workdo\Timesheet\Entities\Timesheet;

/**
 * TimesheetEmailService
 *
 * Procesa buzones IMAP buscando correos cuyo remitente (From) esté mapeado
 * en timesheet_inbound_sender_maps, y crea registros Timesheet.
 *
 * Idéntico en arquitectura a TicketInboundEmailService:
 *  - Tabla propia de buzones:      timesheet_inbound_mailboxes
 *  - Tabla propia de mapas:        timesheet_inbound_sender_maps
 *  - Tabla propia de idempotencia: timesheet_inbound_processed
 *
 * Formato del asunto (cualquiera de estos):
 *   2026-04-07 22:00 to 2026-04-08 06:00   ← rango con dos fechas completas
 *   2026-04-07 22:00 - 2026-04-08 06:00
 *   2026-04-07T22:00 - 2026-04-08T06:00
 *   2026-04-07 22:00-06:00                 ← rango corto mismo día (cruza medianoche si to < from)
 *   22:00-06:00 2026-04-07
 *   22:00 to 06:00 on 2026-04-07
 *
 * Cuerpo del correo → descripción del timesheet.
 * Si el rango abarca N días → se crea UN timesheet por día calendario.
 */
class TimesheetEmailService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Punto de entrada público
    // ─────────────────────────────────────────────────────────────────────────

    public function processMailbox(TimesheetInboundMailbox $mailbox): int
    {
        $password = Crypt::decryptString($mailbox->password_enc);
        $flags    = $mailbox->imap_ssl ? '/imap/ssl' : '/imap/notls';

        $mboxString = sprintf(
            '{%s:%d%s}%s',
            $mailbox->imap_host,
            $mailbox->imap_port,
            $flags,
            $mailbox->folder ?: 'INBOX'
        );

        $inbox = @imap_open($mboxString, $mailbox->username, $password);
        if (!$inbox) {
            throw new \RuntimeException('IMAP connection failed: ' . imap_last_error());
        }

        $emails = imap_search($inbox, 'UNSEEN');
        if (!$emails) {
            imap_close($inbox);
            return 0;
        }

        $processed = 0;

        foreach ($emails as $emailNo) {
            $messageId = null;
            $uid       = null;

            try {
                $rawHeader = imap_fetchheader($inbox, $emailNo);
                $header    = imap_headerinfo($inbox, $emailNo);

                $messageId = $this->extractMessageId($rawHeader)
                    ?: ('ts-imap-' . $mailbox->id . '-' . imap_uid($inbox, $emailNo));
                $uid = (string) imap_uid($inbox, $emailNo);

                // ── 1. Idempotencia ───────────────────────────────────────────
                if ($this->alreadyProcessed($mailbox->id, $messageId)) {
                    imap_setflag_full($inbox, (string) $emailNo, '\\Seen');
                    continue;
                }

                // ── 2. Resolver remitente real ────────────────────────────────
                $realSender = $this->resolveRealSender($inbox, $emailNo, $header, $rawHeader);
                $fromEmail  = strtolower(trim((string) ($realSender['email'] ?? '')));

                // ── 3. Buscar mapa de remitente ───────────────────────────────
                $map = $this->resolveSenderMap($mailbox->id, $fromEmail);

                if ($map === null) {
                    // No está en el mapa → no es un correo de timesheet.
                    // Lo marcamos procesado con status skipped_no_map para no
                    // reintentar, pero NO lo interferimos con otros servicios.
                    $this->markProcessed($mailbox->id, $messageId, $uid, $fromEmail, 'skipped_no_map', 0);
                    imap_setflag_full($inbox, (string) $emailNo, '\\Seen');
                    continue;
                }

                // ── 4. Resolver empleado ──────────────────────────────────────
                // Prioridad: lo que diga el mapa → luego buscar por from_email
                $employeeUserId = $map['employee_user_id'];

                if (!$employeeUserId && $fromEmail !== '') {
                    $employeeUserId = DB::table('users')
                        ->whereRaw('LOWER(email) = ?', [$fromEmail])
                        ->value('id');
                }

                if (!$employeeUserId) {
                    Log::warning('[TimesheetEmailService] Remitente no resuelto como empleado', [
                        'from_email' => $fromEmail,
                        'mailbox_id' => $mailbox->id,
                        'message_id' => $messageId,
                    ]);
                    $this->markProcessed($mailbox->id, $messageId, $uid, $fromEmail, 'skipped_no_employee', 0);
                    imap_setflag_full($inbox, (string) $emailNo, '\\Seen');
                    continue;
                }

                $employee = DB::table('users')->where('id', $employeeUserId)->first();
                if (!$employee) {
                    $this->markProcessed($mailbox->id, $messageId, $uid, $fromEmail, 'skipped_no_employee', 0);
                    imap_setflag_full($inbox, (string) $emailNo, '\\Seen');
                    continue;
                }

                // ── 5. Parsear asunto → rango de fecha/hora ──────────────────
                $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
                
                // primero obtén el body
                $bodyData = $this->getBodyText($inbox, $emailNo);
                
                $rawBody = (string) ($bodyData['body'] ?? '');
                $cleanBody = $this->cleanBodyForDescription(
                    $rawBody,
                    (bool) ($bodyData['is_html'] ?? false)
                );
                
                // 1. intentar por subject
                $range = $this->parseDateTimeRange($subject);
                
                // 2. si falla, intentar por body
                if (!$range) {
                    $range = $this->parseDateTimeRangeFromBody($cleanBody);
                }
                
                if (!$range) {
                    Log::warning('[TimesheetEmailService] No se pudo parsear rango ni del asunto ni del body', [
                        'subject'    => $subject,
                        'body'       => mb_substr($cleanBody, 0, 500),
                        'message_id' => $messageId,
                    ]);
                    $this->markProcessed($mailbox->id, $messageId, $uid, $fromEmail, 'skipped_parse_error', 0);
                    imap_setflag_full($inbox, (string) $emailNo, '\\Seen');
                    continue;
                }
                
                [$fromDt, $toDt] = $range;
                
                $description = trim($cleanBody);
                if ($description === '') {
                    $description = $subject ?: 'Timesheet creado vía email';
                }

                // ── 7. Crear timesheets (split multi-día) ─────────────────────
                DB::beginTransaction();

                $timesheets = $this->createTimesheetsFromRange(
                    $employee,
                    $fromDt,
                    $toDt,
                    $description,
                    $map['project_id'],
                    $map['task_id'],
                    $map['customer_id'],
                    $map['customer_client_id']
                );

                $this->markProcessed(
                    $mailbox->id,
                    $messageId,
                    $uid,
                    $fromEmail,
                    'ok',
                    count($timesheets)
                );

                DB::commit();

                $this->finalizeEmail($inbox, $emailNo, $mailbox);

                $processed++;

            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                Log::error('[TimesheetEmailService] Error procesando email', [
                    'mailbox_id' => $mailbox->id,
                    'email_no'   => $emailNo,
                    'message_id' => $messageId,
                    'error'      => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);

                // Igual que TicketInboundEmailService:
                // NO marcar como procesado → se reintentará en el próximo ciclo.
            }
        }

        imap_close($inbox);

        return $processed;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolución del mapa de remitentes (análogo a resolveProjectMap)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca en timesheet_inbound_sender_maps el primer registro activo que
     * coincida con el email o dominio del remitente para este mailbox.
     *
     * Prioridad: match exacto de email > match de dominio.
     * Retorna null si no hay coincidencia.
     *
     * @return array{employee_user_id:int|null, project_id:int|null, task_id:int|null,
     *               customer_id:int|null, customer_client_id:int|null, workspace_id:int|null}|null
     */
    private function resolveSenderMap(int $mailboxId, string $fromEmail): ?array
    {
        if ($fromEmail === '') {
            return null;
        }

        $maps = TimesheetInboundSenderMap::where('is_active', 1)
            ->where(function ($q) use ($mailboxId) {
                $q->whereNull('mailbox_id')->orWhere('mailbox_id', $mailboxId);
            })
            ->orderBy('sort_order')
            ->get();

        // Paso 1: match exacto de email
        foreach ($maps as $map) {
            if ($map->match_type === 'email' && strtolower($map->match_value) === $fromEmail) {
                return $this->mapToArray($map);
            }
        }

        // Paso 2: match de dominio (@acme.com)
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

    private function mapToArray(TimesheetInboundSenderMap $map): array
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

    // ─────────────────────────────────────────────────────────────────────────
    // Creación de timesheets con split multi-día
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Divide fromDt→toDt en días calendario y crea un Timesheet por día.
     *
     * Ejemplo: 2026-04-07 22:00 → 2026-04-08 06:00
     *   Día 1: 2026-04-07  22:00-23:59  (1h 59m)
     *   Día 2: 2026-04-08  00:00-06:00  (6h 00m)
     *
     * @return Timesheet[]
     */
    private function createTimesheetsFromRange(
        object $employee,
        Carbon $fromDt,
        Carbon $toDt,
        string $description,
        ?int   $projectId,
        ?int   $taskId,
        ?int   $customerId,
        ?int   $customerClientId
    ): array {
        $timesheets = [];
        $fromDay    = $fromDt->copy()->startOfDay();
        $toDay      = $toDt->copy()->startOfDay();
        $cursor     = $fromDay->copy();

        while ($cursor->lte($toDay)) {
            $dayFrom = $cursor->eq($fromDay)
                ? $fromDt->copy()
                : $cursor->copy()->setTime(0, 0, 0);

            $dayTo = $cursor->eq($toDay)
                ? $toDt->copy()
                : $cursor->copy()->setTime(23, 59, 0);

            $diffMinutes = (int) $dayFrom->diffInMinutes($dayTo);

            if ($diffMinutes > 0) {
                $ts = Timesheet::create([
                    'user_id'            => $employee->id,
                    'workspace_id'       => $employee->workspace_id ?? null,
                    'created_by'         => $employee->id,
                    'date'               => $cursor->toDateString(),
                    'from_time'          => $dayFrom->format('H:i'),
                    'to_time'            => $dayTo->format('H:i'),
                    'hours'              => intdiv($diffMinutes, 60),
                    'minutes'            => $diffMinutes % 60,
                    'description'        => $description,
                    'type'               => 'Manually',
                    'project_id'         => $projectId,
                    'task_id'            => $taskId,
                    'customer_id'        => $customerId,
                    'customer_client_id' => $customerClientId,
                    'validated'          => false,
                    'invoiced'           => false,
                    'paid'               => false,
                ]);

                $timesheets[] = $ts;

                Log::info('[TimesheetEmailService] Timesheet creado', [
                    'id'      => $ts->id,
                    'user_id' => $employee->id,
                    'date'    => $cursor->toDateString(),
                    'from'    => $dayFrom->format('H:i'),
                    'to'      => $dayTo->format('H:i'),
                ]);
            }

            $cursor->addDay();
        }

        return $timesheets;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parseo de rango fecha/hora desde el asunto
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function parseDateTimeRange(string $subject): ?array
    {
        $s = trim($subject);
    
        // -------------------------------------------------
        // Patrón 0: buscar líneas "Inicio/Fin" o "Start/End"
        // Formato esperado:
        // Inicio   : 2026-04-09 08:00 AM
        // Fin      : 2026-04-09 05:00 PM
        // -------------------------------------------------
        $fromText = null;
        $toText   = null;
    
        if (preg_match('/(?:^|\R)\s*(Inicio|Start)\s*:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+[AP]M)\s*$/imu', $s, $m)) {
            $fromText = trim($m[2]);
        }
    
        if (preg_match('/(?:^|\R)\s*(Fin|End)\s*:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+[AP]M)\s*$/imu', $s, $m)) {
            $toText = trim($m[2]);
        }
    
        if ($fromText && $toText) {
            try {
                $from = Carbon::createFromFormat('Y-m-d h:i A', $fromText);
                $to   = Carbon::createFromFormat('Y-m-d h:i A', $toText);
    
                if ($from && $to) {
                    if ($to->lte($from)) {
                        $to->addDay();
                    }
    
                    return [$from, $to];
                }
            } catch (\Throwable $e) {
                // sigue a los demás patrones
            }
        }
    
        // -------------------------------------------------
        // Patrón 1: dos fechas completas
        // "YYYY-MM-DD HH:MM to YYYY-MM-DD HH:MM"
        // "YYYY-MM-DD HH:MM - YYYY-MM-DD HH:MM"
        // -------------------------------------------------
        if (preg_match(
            '/(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})\s*(?:to|-{1,3})\s*(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/i',
            $s,
            $m
        )) {
            try {
                $from = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[2]);
                $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[4]);
    
                if ($from && $to && $to->gt($from)) {
                    return [$from, $to];
                }
            } catch (\Throwable $e) {
                // sigue al próximo patrón
            }
        }
    
        // -------------------------------------------------
        // Patrón 2: fecha + rango corto
        // "YYYY-MM-DD HH:MM-HH:MM"
        // -------------------------------------------------
        if (preg_match('/(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/', $s, $m)) {
            try {
                $from = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[2]);
                $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[1] . ' ' . $m[3]);
    
                if ($from && $to) {
                    if ($to->lte($from)) {
                        $to->addDay();
                    }
    
                    return [$from, $to];
                }
            } catch (\Throwable $e) {
                // sigue al próximo patrón
            }
        }
    
        // -------------------------------------------------
        // Patrón 3: horas primero luego fecha
        // "HH:MM-HH:MM YYYY-MM-DD"
        // "HH:MM to HH:MM on YYYY-MM-DD"
        // -------------------------------------------------
        if (preg_match('/(\d{2}:\d{2})\s*(?:to|-)\s*(\d{2}:\d{2})\s*(?:on\s+)?(\d{4}-\d{2}-\d{2})/i', $s, $m)) {
            try {
                $from = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[1]);
                $to   = Carbon::createFromFormat('Y-m-d H:i A', $m[3] . ' ' . $m[2]);
    
                if ($from && $to) {
                    if ($to->lte($from)) {
                        $to->addDay();
                    }
    
                    return [$from, $to];
                }
            } catch (\Throwable $e) {
                    // sigue al final
            }
        }
    
        return null;
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
    // ─────────────────────────────────────────────────────────────────────────
    // Idempotencia
    // ─────────────────────────────────────────────────────────────────────────

    private function alreadyProcessed(int $mailboxId, string $messageId): bool
    {
        return DB::table('timesheet_inbound_processed')
            ->where('mailbox_id', $mailboxId)
            ->where('message_id', $messageId)
            ->exists();
    }

    private function markProcessed(
        int    $mailboxId,
        string $messageId,
        string $uid,
        string $fromEmail,
        string $status,
        int    $timesheetsCreated
    ): void {
        DB::table('timesheet_inbound_processed')->insertOrIgnore([
            'mailbox_id'         => $mailboxId,
            'message_id'         => $messageId,
            'imap_uid'           => $uid,
            'from_email'         => $fromEmail,
            'status'             => $status,
            'timesheets_created' => $timesheetsCreated,
            'processed_at'       => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cuerpo del email
    // ─────────────────────────────────────────────────────────────────────────

    private function getBodyText($inbox, int $emailNo): array
    {
        $structure = imap_fetchstructure($inbox, $emailNo);

        $html = $this->findPart($inbox, $emailNo, $structure, 'HTML');
        if ($html !== null) {
            $html = $this->decodeHtmlPart($html);
            if ($html !== '' && !$this->looksLikeBase64Garbage(strip_tags($html))) {
                return ['body' => $html, 'is_html' => true];
            }
        }

        $plain = $this->findPart($inbox, $emailNo, $structure, 'PLAIN');
        if ($plain !== null) {
            $plain = $this->normalizeEmailText($plain);
            if ($plain !== '' && !$this->looksLikeBase64Garbage($plain)) {
                return ['body' => $plain, 'is_html' => false];
            }
        }

        return ['body' => '', 'is_html' => false];
    }

    private function cleanBodyForDescription(string $body, bool $isHtml): string
    {
        if ($isHtml) {
            $body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $body);
            $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $body);
            $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
            $body = preg_replace('/<\/p>/i', "\n\n", $body);
            $body = preg_replace('/<\/div>/i', "\n", $body);
            $body = strip_tags($body);
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $body = $this->normalizeEmailText($body);

        foreach ([
            '/\nOn .+?wrote:\s*$/is',
            '/\nEl .+?escribi[oó]:\s*$/is',
            '/\nFrom:\s.+$/is',
            '/\nDe:\s.+$/is',
            '/\n-+\s*Original Message\s*-+$/is',
        ] as $pattern) {
            $parts = preg_split($pattern, $body, 2);
            if (count($parts) > 1 && !empty($parts[0])) {
                $body = $parts[0];
                break;
            }
        }

        $body = preg_replace("/\n{3,}/", "\n\n", trim($body));

        if (mb_strlen($body) > 2000) {
            $body = mb_substr($body, 0, 1997) . '...';
        }

        return trim($body);
    }

    private function findPart($inbox, int $emailNo, $structure, string $wantSubtype, string $partNumber = '')
    {
        $wantSubtype = strtoupper($wantSubtype);

        if (($structure->type ?? null) === 0 && strtoupper($structure->subtype ?? '') === $wantSubtype) {
            $section = $partNumber !== '' ? $partNumber : '1';
            $data    = imap_fetchbody($inbox, $emailNo, $section, FT_PEEK);
            if ($data === false || $data === '') {
                $data = imap_body($inbox, $emailNo, FT_PEEK);
            }
            return $this->decodePart($data, (int) ($structure->encoding ?? 0));
        }

        if (!empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $num    = $partNumber === '' ? (string)($index + 1) : $partNumber . '.' . ($index + 1);
                $result = $this->findPart($inbox, $emailNo, $part, $wantSubtype, $num);
                if ($result !== null && trim($result) !== '') {
                    return $result;
                }
            }
        }

        return null;
    }

    private function decodePart(string $data, int $encoding): string
    {
        return match ($encoding) {
            3       => base64_decode($data),
            4       => quoted_printable_decode($data),
            default => $data,
        };
    }

    private function decodeHtmlPart(string $html): string
    {
        $html = quoted_printable_decode($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/=3D/i', '=', $html);
        $html = preg_replace('/=\r?\n/', '', $html);
        return $html;
    }

    private function normalizeEmailText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = quoted_printable_decode($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/=3D/i', '=', $text);
        $text = preg_replace('/=\n/', '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return trim($text);
    }

    private function looksLikeBase64Garbage(string $text): bool
    {
        $sample = substr(trim($text), 0, 1200);
        if (preg_match('/\b(iVBORw0KGgo|\/9j\/|R0lGODlh)/i', $sample)) {
            return true;
        }
        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $sample)) {
            if (strlen(preg_replace('/\s+/', '', $sample)) > 300) {
                return true;
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers IMAP (ported 1:1 de TicketInboundEmailService)
    // ─────────────────────────────────────────────────────────────────────────

    private function extractMessageId(string $rawHeader): ?string
    {
        if (preg_match('/^Message-ID:\s*(.+)$/mi', $rawHeader, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function resolveRealSender($inbox, int $emailNo, $header, ?string $rawHeader = null): array
    {
        try {
            $rawHeader = $rawHeader ?: imap_fetchheader($inbox, $emailNo);

            $original = $this->extractAddressFromRawHeader($rawHeader, 'X-Original-From');
            if (!empty($original['email'])) {
                return $original;
            }

            $replyTo = $this->parseAddress($header->reply_to ?? []);
            if (!empty($replyTo[0]['email'])) {
                return ['email' => $replyTo[0]['email'], 'name' => $replyTo[0]['name'] ?? null];
            }

            $from = $this->parseAddress($header->from ?? []);
            if (!empty($from[0]['email'])) {
                return ['email' => $from[0]['email'], 'name' => $from[0]['name'] ?? null];
            }
        } catch (\Throwable $e) {
            Log::warning('[TimesheetEmailService] resolveRealSender error', ['error' => $e->getMessage()]);
        }

        return ['email' => null, 'name' => null];
    }

    private function parseAddress(array $arr): array
    {
        $result = [];
        foreach ($arr as $a) {
            $email = strtolower(($a->mailbox ?? '') . '@' . ($a->host ?? ''));
            $name  = isset($a->personal) ? imap_utf8($a->personal) : null;
            if ($email !== '@') {
                $result[] = ['email' => $email, 'name' => $name ?: null];
            }
        }
        return $result;
    }

    private function extractAddressFromRawHeader(string $rawHeader, string $headerName): array
    {
        $pattern = '/^' . preg_quote($headerName, '/') . ':\s*(.+)$/mi';
        if (!preg_match($pattern, $rawHeader, $m)) {
            return ['email' => null, 'name' => null];
        }
        $value = trim($m[1]);

        if (preg_match('/^(.*)<\s*([^>]+)\s*>$/', $value, $parts)) {
            $name  = trim(trim($parts[1]), '"\'');
            $email = strtolower(trim($parts[2]));
            return [
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'name'  => $name !== '' ? imap_utf8($name) : null,
            ];
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($value), 'name' => null];
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $em)) {
            return ['email' => strtolower(trim($em[0])), 'name' => null];
        }

        return ['email' => null, 'name' => null];
    }

    private function finalizeEmail($inbox, int $emailNo, TimesheetInboundMailbox $mailbox): void
    {
        imap_setflag_full($inbox, (string) $emailNo, '\\Seen');

        if (!empty($mailbox->processed_folder)) {
            @imap_mail_move($inbox, (string) $emailNo, $mailbox->processed_folder);
            @imap_expunge($inbox);
        }
    }
}
