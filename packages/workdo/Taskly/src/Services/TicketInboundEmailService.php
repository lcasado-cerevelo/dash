<?php

namespace Workdo\Taskly\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Entities\Comment;
use Workdo\Taskly\Entities\Task;
use Workdo\Taskly\Entities\TicketInboundMailbox;
use Workdo\Taskly\Entities\TicketInboundProjectMap;
use Workdo\Taskly\Entities\TicketInboundRoute;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\HelpdeskTicketService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TicketInboundEmailService
{
    public function processMailbox(TicketInboundMailbox $mailbox): int
    {
        $password = Crypt::decryptString($mailbox->password_enc);
    
        $flags = $mailbox->imap_ssl ? '/imap/ssl' : '/imap/notls';
    
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
    
        $routes = TicketInboundRoute::where('mailbox_id', $mailbox->id)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    
        $processed = 0;
    
        foreach ($emails as $emailNo) {
            $savedAttachments = [];
            $messageId = null;
            $uid = null;
    
            try {
                $rawHeader   = imap_fetchheader($inbox, $emailNo);
                $inReplyTo   = $this->extractHeaderValue($rawHeader, 'In-Reply-To');
                $references  = $this->extractHeaderValue($rawHeader, 'References');
                $header      = imap_headerinfo($inbox, $emailNo);
    
                $messageId = $this->extractMessageId($rawHeader) ?: 'imap-' . $mailbox->id . '-' . imap_uid($inbox, $emailNo);
                $uid       = (string) imap_uid($inbox, $emailNo);
    
                if ($this->alreadyProcessed($mailbox->id, $messageId)) {
                    imap_setflag_full($inbox, (string) $emailNo, "\\Seen");
                    continue;
                }
    
                $emailReceivedAt = $this->parseEmailReceivedAt($header);
                $realSender      = $this->resolveRealSender($inbox, $emailNo, $header, $rawHeader);
                $bodyData        = $this->getBodyText($inbox, $emailNo);
    
                $msg = [
                    'from'         => $realSender['email'] ?? $this->fmtAddress($header->from ?? []),
                    'from_email'   => $realSender['email'] ?? null,
                    'from_name'    => $realSender['name'] ?? null,
                    'to'           => $this->fmtAddress($header->to ?? []),
                    'cc'           => $this->fmtAddress($header->cc ?? []),
                    'subject'      => isset($header->subject) ? imap_utf8($header->subject) : 'Unknown Subject',
                    'body'         => $bodyData['body'] ?? '',
                    'body_is_html' => (bool)($bodyData['is_html'] ?? false),
                    'message_id'   => $messageId,
                    'in_reply_to'  => $inReplyTo,
                    'references'   => $references,
                    'imap_uid'     => $uid,
                    'received_at'  => $emailReceivedAt,
                ];
    
                $existingTicket = $this->findExistingHelpdeskTicketFromHeaders($msg);
    
                if (!$existingTicket) {
                    $existingTicket = $this->findExistingHelpdeskTicketByReply($msg);
                }
    
                $existingTaskId = $this->extractTaskIdFromSubject($msg['subject']);
                if ($existingTaskId) {
                    DB::beginTransaction();
    
                    $this->appendTaskComment($existingTaskId, $msg);
                    $this->markProcessed($mailbox->id, $messageId, $uid);
    
                    DB::commit();
    
                    $this->finalizeEmail($inbox, $emailNo, $mailbox);
                    $processed++;
                    continue;
                }
    
                $route = $this->resolveRoute($routes, $msg);
    
                if ($route && !$route->allow_new_ticket) {
                    DB::beginTransaction();
    
                    $this->markProcessed($mailbox->id, $messageId, $uid);
    
                    DB::commit();
    
                    $this->finalizeEmail($inbox, $emailNo, $mailbox);
                    $processed++;
                    continue;
                }
    
                $map = $this->resolveProjectMap($mailbox->id, $msg['from_email'] ?? '');
    
                $match_name       = $map['match_name'] ?? null;
                $customerId       = $map['customer_id'] ?? null;
                $customerClientId = $map['customer_client_id'] ?? null;
                $taskId           = $map['task_id'] ?? null;
                $workspaceId      = $map['workspace_id'] ?? null;
                $categoryId       = $map['category_id'] ?? null;
                $priority         = $map['priority'] ?? null;
                $assignTo         = $map['assign_to'] ?? null;
    
                $structure = imap_fetchstructure($inbox, $emailNo);
                $attachments = $this->extractAttachments($inbox, $emailNo, $structure);
    
                $filteredAttachments = [];
                foreach ($attachments as $attachment) {
                    if ($this->shouldSkipAttachment($attachment)) {
                        continue;
                    }
    
                    $filteredAttachments[] = $attachment;
                }
    
                DB::beginTransaction();
    
                $savedAttachments = $this->saveHelpdeskAttachments($filteredAttachments);
    
                $visibleToUserIds = array_merge(
                    $this->resolveVisibleUserIdsFromEmails($msg['to'] ?? ''),
                    $this->resolveVisibleUserIdsFromEmails($msg['cc'] ?? '')
                );
    
                if (!empty($assignTo)) {
                    $visibleToUserIds[] = (int) $assignTo;
                }
    
                $visibleToUserIds = array_values(array_unique(array_map('intval', $visibleToUserIds)));
    
                if ($existingTicket) {
                    $this->appendHelpdeskReply($existingTicket, $msg, $savedAttachments);
                } else {
                    $this->createNewHelpdeskTicket(
                        $msg,
                        $match_name,
                        $taskId,
                        $customerId,
                        $customerClientId,
                        $workspaceId,
                        $categoryId,
                        $priority,
                        $assignTo,
                        $savedAttachments,
                        $visibleToUserIds,
                        $mailbox->id
                    );
                }
    
                $this->markProcessed($mailbox->id, $messageId, $uid);
    
                DB::commit();
    
                $this->finalizeEmail($inbox, $emailNo, $mailbox);
    
                $processed++;
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
    
                if (!empty($savedAttachments)) {
                    try {
                        $this->deleteSavedAttachments($savedAttachments);
                    } catch (\Throwable $cleanupError) {
                        \Log::warning('Inbound email attachment cleanup failed', [
                            'mailbox_id'     => $mailbox->id,
                            'email_no'       => $emailNo,
                            'message_id'     => $messageId,
                            'cleanup_error'  => $cleanupError->getMessage(),
                        ]);
                    }
                }
    
                echo "  ERROR - " . $e->getMessage() . "\n";
    
                \Log::error('Email inbound error', [
                    'mailbox_id' => $mailbox->id,
                    'email_no'   => $emailNo,
                    'message_id' => $messageId,
                    'imap_uid'   => $uid,
                    'error'      => $e->getMessage(),
                ]);
    
                // Importante:
                // NO marcar como procesado
                // NO marcar como leído
                // NO mover de carpeta
                continue;
            }
        }
    
        imap_close($inbox);
    
        return $processed;
    }
    private function createNewTask(
        array $msg,
        int $projectId,
        ?int $workspaceId = null,
        ?string $priority = null,
        ?string $assignTo = null
    ): int
    {
        $task = new Task();
        $task->title = $msg['subject'];
        $task->description = $this->formatDescription($msg);
        $task->project_id = $projectId;
        $task->priority = $priority ?: 'medium';
        $task->assign_to = $assignTo ?: '';
        $task->status = 'todo';
        $task->workspace = $workspaceId ?? 1;
        $task->save();
    
        return $task->id;
    }

    private function resolveProjectMap(int $mailboxId, string $fromEmailsCsv): array
    {
        try {
            $fromList = array_filter(array_map('trim', explode(',', strtolower($fromEmailsCsv))));
            $firstEmail = $fromList[0] ?? null;
    
            if (!$firstEmail) {
                return [
                    'match_name' => null,
                    'customer_id' => null,
                    'customer_client_id' => null,
                    'task_id' => null,
                    'workspace_id' => null,
                    'category_id' => null,
                    'priority' => null,
                    'assign_to' => null,
                ];
            }
    
            $maps = TicketInboundProjectMap::where('is_active', 1)
                ->where(function ($q) use ($mailboxId) {
                    $q->whereNull('mailbox_id')->orWhere('mailbox_id', $mailboxId);
                })
                ->orderBy('sort_order')
                ->get();
    
            foreach ($maps as $map) {
                if ($map->match_type === 'email' && strtolower($map->match_value) === $firstEmail) {
                    return [
                        'match_name' => $map->match_name ?: null,
                        'customer_id' => $map->customer_id ? (int) $map->customer_id : null,
                        'customer_client_id' => $map->customer_client_id ? (int) $map->customer_client_id : null,
                        'task_id' => $map->task_id ? (int) $map->task_id : null,
                        'workspace_id' => $map->workspace_id ? (int) $map->workspace_id : null,
                        'category_id' => $map->category_id ? (int) $map->category_id : null,
                        'priority' => $map->priority,
                        'assign_to' => $map->assign_to,
                    ];
                }
            }
    
            $domain = strstr($firstEmail, '@');
    
            if ($domain) {
                foreach ($maps as $map) {
                    if ($map->match_type === 'domain' && strtolower($map->match_value) === strtolower($domain)) {
                        return [
                            'match_name' => $map->match_name ?: null,
                            'customer_id' => $map->customer_id ? (int) $map->customer_id : null,
                            'customer_client_id' => $map->customer_client_id ? (int) $map->customer_client_id : null,
                            'task_id' => $map->task_id ? (int) $map->task_id : null,
                            'workspace_id' => $map->workspace_id ? (int) $map->workspace_id : null,
                            'category_id' => $map->category_id ? (int) $map->category_id : null,
                            'priority' => $map->priority,
                            'assign_to' => $map->assign_to,
                        ];
                    }
                }
            }
    
            return [
                'match_name' => null,
                'customer_id' => null,
                'customer_client_id' => null,
                'task_id' => null,
                'workspace_id' => null,
                'category_id' => null,
                'priority' => null,
                'assign_to' => null,
            ];
    
        } catch (\Throwable $e) {
            throw new \RuntimeException('[resolveProjectMap] ' . $e->getMessage(), 0, $e);
        }
    }

    private function resolveRoute($routes, array $msg): ?TicketInboundRoute
    {
        foreach ($routes as $route) {
            if ($this->routeMatches($route, $msg)) {
                return $route;
            }
        }
        return null;
    }

    private function routeMatches(TicketInboundRoute $r, array $msg): bool
    {
        if ($r->to_email) {
            $toList = array_map('trim', explode(',', $msg['to']));
            if (!in_array(strtolower($r->to_email), $toList, true)) {
                return false;
            }
        }

        if ($r->to_contains && !str_contains($msg['to'], strtolower($r->to_contains))) {
            return false;
        }

        if ($r->from_contains && !str_contains($msg['from'], strtolower($r->from_contains))) {
            return false;
        }

        if ($r->subject_contains && !str_contains(strtolower($msg['subject']), strtolower($r->subject_contains))) {
            return false;
        }

        return true;
    }

    private function appendTaskComment(int $taskId, array $msg): void
    {
        try {
            $comment = new Comment();
            $comment->task_id = $taskId;
            $comment->comment = $this->formatDescription($msg);
            $comment->created_by = 1;
            $comment->user_type = 'User';
            $comment->save();
        } catch (\Throwable $e) {
            throw new \RuntimeException('[appendTaskComment] ' . $e->getMessage(), 0, $e);
        }
    }

    private function formatDescription(array $msg): string
    {
        return
            "From: {$msg['from']}\n" .
            "To: {$msg['to']}\n" .
            ($msg['cc'] ? "Cc: {$msg['cc']}\n" : '') .
            "Subject: {$msg['subject']}\n" .
            "Message-ID: {$msg['message_id']}\n" .
            "----------------------------------------\n" .
            $msg['body'];
    }

    private function extractTaskIdFromSubject(string $subject): ?int
    {
        if (preg_match('/\[TASK-(\d+)\]/i', $subject, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function extractMessageId(string $rawHeader): ?string
    {
        if (preg_match('/^Message-ID:\s*(.+)$/mi', $rawHeader, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function fmtAddress(array $arr): string
    {
        $out = [];
        foreach ($arr as $a) {
            $email = strtolower(($a->mailbox ?? '') . '@' . ($a->host ?? ''));
            if ($email !== '@') {
                $out[] = $email;
            }
        }
        return implode(',', $out);
    }

    private function getBodyText($inbox, int $emailNo): array
    {
        $structure = imap_fetchstructure($inbox, $emailNo);
    
        // 1. Preferir HTML real
        $html = $this->findPart($inbox, $emailNo, $structure, 'HTML');
        if ($html !== null) {
            $html = $this->normalizeInboundHtml($html);
            $html = $this->removeNoiseLinesFromHtml($html);
    
            if ($html !== '' && !$this->looksLikeBase64Garbage(strip_tags($html))) {
                return [
                    'body'    => $html,
                    'is_html' => true,
                ];
            }
        }
    
        // 2. Fallback a plain text
        $plain = $this->findPart($inbox, $emailNo, $structure, 'PLAIN');
        if ($plain !== null) {
            $plain = $this->normalizeEmailText($plain);
            $plain = $this->removeNoiseLines($plain);
    
            if ($plain !== '' && !$this->looksLikeBase64Garbage($plain)) {
                return [
                    'body'    => $plain,
                    'is_html' => false,
                ];
            }
        }
    
        return [
            'body'    => '',
            'is_html' => false,
        ];
    }

    private function removeNoiseLines(string $text): string
    {
        $patterns = [
            '/^To unsubscribe from this group and stop receiving emails from it.*$/i',
        ];
    
        $lines = preg_split('/\r\n|\r|\n/', $text);
    
        $filtered = array_filter($lines, function ($line) use ($patterns) {
            $line = trim($line);
    
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    return false;
                }
            }
    
            return true;
        });
    
        // Quitar líneas vacías repetidas
        $result = implode("\n", $filtered);
        $result = preg_replace("/\n{3,}/", "\n\n", $result);
    
        return trim($result);
    }

    private function looksLikeBase64Garbage(string $text): bool
    {
        $sample = substr(trim($text), 0, 1200);
    
        // Firmas típicas de PNG/JPG/GIF/base64
        if (preg_match('/\b(iVBORw0KGgo|\/9j\/|R0lGODlh|Qk0[A-Za-z0-9+\/=]*)/i', $sample)) {
            return true;
        }
    
        // Demasiados caracteres base64 corridos
        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $sample)) {
            $compact = preg_replace('/\s+/', '', $sample);
            if (strlen($compact) > 300) {
                return true;
            }
        }
    
        return false;
    }
    
    private function htmlToVisibleText(string $html): string
    {
        $html = quoted_printable_decode($html);
        $html = $this->decodeMimeString($html);
    
        // Quitar bloques no visibles
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
    
        // Quitar imágenes completas
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);
    
        // Convertir saltos visuales
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/p>/i', "\n\n", $html);
        $html = preg_replace('/<\/div>/i', "\n", $html);
        $html = preg_replace('/<\/li>/i', "\n", $html);
    
        // Quitar tags
        $html = strip_tags($html);
    
        $html = $this->normalizeEmailText($html);
    
        // Limpiar líneas vacías excesivas
        $html = preg_replace("/\n{3,}/", "\n\n", $html);
    
        return trim($html);
    }
    
    private function decodeMimeString(string $text): string
    {
        $elements = imap_mime_header_decode($text);
        $decoded = '';
    
        foreach ($elements as $element) {
            $charset = strtoupper($element->charset ?? 'UTF-8');
            $part = $element->text ?? '';
    
            if ($charset !== 'DEFAULT' && $charset !== 'UTF-8') {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $part);
                $decoded .= $converted !== false ? $converted : $part;
            } else {
                $decoded .= $part;
            }
        }
    
        return $decoded !== '' ? $decoded : $text;
    }

    private function normalizeEmailText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = quoted_printable_decode($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
        // arreglar secuencias raras tipo =3D que sobreviven
        $text = preg_replace('/=3D/i', '=', $text);
        $text = preg_replace('/=\n/', '', $text);
    
        // quitar caracteres invisibles molestos
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    
        return trim($text);
    }
    
    private function normalizeHtmlEmail(string $html): string
    {
        $html = $this->decodeHtmlPart($html);
    
        // Quitar bloques que no aportan
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html);
    
        // Quitar imágenes inline / firmas gráficas
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);
    
        // Convertir elementos de bloque a saltos de línea ANTES de strip_tags
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\s*\/p\s*>/i', "\n\n", $html);
        $html = preg_replace('/<\s*\/div\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*\/li\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*\/tr\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*(p|div|li|tr|table|section|article|ul|ol)\b[^>]*>/i', '', $html);
    
        // Quitar tags restantes
        $html = strip_tags($html);
    
        // Normalizar entidades y espacios
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = str_replace("\xc2\xa0", ' ', $html); // nbsp UTF-8
        $html = preg_replace("/[ \t]+/u", ' ', $html);
    
        $html = $this->normalizeEmailText($html);
        $html = $this->cleanEmailBody($html);
    
        return trim($html);
    }
    
    private function decodeHtmlPart(string $html): string
    {
        $html = quoted_printable_decode($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
        // Reparar restos típicos
        $html = preg_replace('/=3D/i', '=', $html);
        $html = preg_replace('/=\r?\n/', '', $html);
    
        return $html;
    }
    
    private function extractAttachments($inbox, int $emailNo, $structure): array
    {
        try {
            $attachments = [];
    
            if (empty($structure->parts)) {
                return $attachments;
            }
    
            foreach ($structure->parts as $index => $part) {
                $isAttachment = false;
                $filename = '';
    
                if (!empty($part->ifdparameters)) {
                    foreach ($part->dparameters as $param) {
                        if (strtolower($param->attribute) === 'filename') {
                            $filename = $param->value;
                            $isAttachment = true;
                        }
                    }
                }
    
                if (!empty($part->ifparameters)) {
                    foreach ($part->parameters as $param) {
                        if (strtolower($param->attribute) === 'name') {
                            $filename = $param->value;
                            $isAttachment = true;
                        }
                    }
                }
    
                if (!empty($part->disposition) && in_array(strtolower($part->disposition), ['attachment', 'inline'])) {
                    $isAttachment = true;
                }
    
                if ($isAttachment && $filename) {
                    $body = imap_fetchbody($inbox, $emailNo, $index + 1, FT_PEEK);
    
                    switch ((int) ($part->encoding ?? 0)) {
                        case 3:
                            $body = base64_decode($body);
                            break;
                        case 4:
                            $body = quoted_printable_decode($body);
                            break;
                    }
    
                    $attachments[] = [
                        'filename' => $filename,
                        'data' => $body,
                    ];
                }
            }
    
            return $attachments;
    
        } catch (\Throwable $e) {
            throw new \RuntimeException('[extractAttachments] ' . $e->getMessage(), 0, $e);
        }
    }
    
    private function saveHelpdeskAttachments(array $attachments): array
    {
        try {
            if (empty($attachments)) {
                return [];
            }
    
            $folderName = time();
            $baseDir = app()->basePath('uploads/helpdesk/' . $folderName);
    
            if (!file_exists($baseDir)) {
                mkdir($baseDir, 0775, true);
            }
    
            $saved = [];
    
            foreach ($attachments as $file) {
                $originalName = $file['filename'] ?? 'file.bin';
                $safeName = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $originalName);
    
                if (!$safeName) {
                    $safeName = uniqid('file_');
                }
    
                $fullPath = $baseDir . '/' . $safeName;
                file_put_contents($fullPath, $file['data']);
    
                $saved[] = [
                    'name' => $safeName,
                    'path' => 'uploads/helpdesk/' . $folderName . '/' . $safeName,
                ];
            }
    
            return $saved;
    
        } catch (\Throwable $e) {
            throw new \RuntimeException('[saveHelpdeskAttachments] ' . $e->getMessage(), 0, $e);
        }
    }
    
    private function createNewHelpdeskTicket(
        array $msg,
        ?string $matchName,
        ?int $taskId,
        ?int $customerId,
        ?int $customerClientId,
        ?int $workspaceId,
        ?int $categoryId,
        $priority,
        $assignTo,
        array $savedAttachments = [],
        array $visibleToUserIds = [],
        ?int $mailboxId = null
    ): int
    {
        try {
            $attachmentJson = json_encode($savedAttachments ?: [], JSON_UNESCAPED_SLASHES);
    
            $publicTicketId = time();
    
            do {
                $exists = DB::table('helpdesk_tickets')
                    ->where('ticket_id', $publicTicketId)
                    ->exists();
    
                if ($exists) {
                    $publicTicketId++;
                }
    
            } while ($exists);
                
            if (!empty($msg['body_is_html'])) {
                $cleanBodyHtml = $this->normalizeInboundHtml((string)($msg['body'] ?? ''));
                $cleanBodyHtml = $this->removeNoiseLinesFromHtml($cleanBodyHtml);
            } else {
                $cleanBody = $this->cleanEmailBody(
                    (string)($msg['body'] ?? ''),
                    (string)($msg['subject'] ?? '')
                );
            
                $cleanBody = $this->removeNoiseLines($cleanBody);
                $cleanBodyHtml = nl2br(e($cleanBody));
            }
            
            $fullDescriptionHtml = $this->buildInboundDescriptionHtml($msg, $cleanBodyHtml);
            $sanitizedDescriptionHtml = $this->sanitizeInboundHtml($fullDescriptionHtml);
            
            $fromEmail = $msg['from_email'] ?? '';
    
            $userId = DB::table('users')
                        ->whereRaw('LOWER(email) = ?', [strtolower($fromEmail)])
                        ->value('id');
    
            $createdBy = $userId ?: 1;
    
            if (!$categoryId || $categoryId === 0) {
                $categoryId = 1;
            }
    
            $receivedAt = !empty($msg['received_at'])
                ? Carbon::parse($msg['received_at'])
                    ->setTimezone('America/Puerto_Rico')
                : now('America/Puerto_Rico');        
    
            $ticketId = DB::table('helpdesk_tickets')->insertGetId([
                'task_id'             => $taskId,
                'customer_id'         => $customerId,
                'customer_client_id'  => $customerClientId,
                'ticket_id'           => $publicTicketId,
                'subject'             => $msg['subject'] ?? '',
                'name'                => $matchName ?: ($msg['from_name'] ?? $msg['from_email'] ?? ''),
                'email'               => $msg['from_email'] ?? '',
                'description'         => $sanitizedDescriptionHtml,
                'category'            => $categoryId,
                'priority'            => $priority ?: '3',
                'status'              => 'New',
                'workspace'           => $workspaceId ?? 1,
                'user_id'             => !empty($assignTo) ? (int) $assignTo : null,
                'visible_to_user_ids' => HelpdeskTicketService::encodeIds($visibleToUserIds),
                'touched_by_user_ids' => HelpdeskTicketService::encodeIds(array_filter([$createdBy])),
                'created_by'          => $createdBy,
                'attachments'         => $attachmentJson,
                'created_at'          => $receivedAt ?? now(),
                'updated_at'          => now(),
            ]);
    
            $this->storeHelpdeskEmailMessage(
                $ticketId,
                null,
                $mailboxId,
                $msg,
                'inbound'
            );
    
            if (!empty($ticketId)) {
                $this->sendAssignedTicketEmailById($ticketId);
            }
    
            return $ticketId;
    
        } catch (\Throwable $e) {
            throw new \RuntimeException('[createNewHelpdeskTicket] ' . $e->getMessage(), 0, $e);
        }
    }
    
    private function cleanEmailBody(string $body, ?string $subject = null): string
    {
        $body = $this->normalizeEmailText($body);
    
        $patterns = [
            '/^From:\s.*$/mi',
            '/^To:\s.*$/mi',
            '/^Cc:\s.*$/mi',
            '/^Bcc:\s.*$/mi',
            '/^Subject:\s.*$/mi',
            '/^Date:\s.*$/mi',
            '/^Message-ID:\s.*$/mi',
            '/^Content-Type:\s.*$/mi',
            '/^Content-Transfer-Encoding:\s.*$/mi',
            '/^MIME-Version:\s.*$/mi',
            '/^charset\s*=.*$/mi',
            '/^boundary\s*=.*$/mi',
            '/^-{2,}.*$/m',
            '/^On .*wrote:$/mi',
            '/^-+\s*Forwarded message\s*-+$/mi',
        ];
    
        $body = preg_replace($patterns, '', $body);
        $body = preg_replace('/\[image:[^\]]+\]/i', '', $body);
        $body = preg_replace('/cid:[^\s>"]+/i', '', $body);
    
        $lines = preg_split('/\n/', $body);
        $cleanLines = [];
        $previous = null;
    
        foreach ($lines as $line) {
            $line = trim($line);
    
            if ($line === '') {
                if ($previous !== '') {
                    $cleanLines[] = '';
                    $previous = '';
                }
                continue;
            }
    
            if (preg_match('/^--[A-Za-z0-9_\-]+$/', $line)) {
                continue;
            }
    
            if (preg_match('/gmail-apple-interchange-newline/i', $line)) {
                continue;
            }
    
            if (preg_match('/^\[image:.*\]$/i', $line)) {
                continue;
            }
    
            // Evitar líneas consecutivas idénticas
            if ($previous !== null && mb_strtolower($line) === mb_strtolower($previous)) {
                continue;
            }
    
            $cleanLines[] = $line;
            $previous = $line;
        }
    
        // Si la primera línea es el subject, quitarla
        if ($subject && !empty($cleanLines)) {
            $firstNonEmptyIndex = null;
    
            foreach ($cleanLines as $i => $line) {
                if (trim($line) !== '') {
                    $firstNonEmptyIndex = $i;
                    break;
                }
            }
    
            if ($firstNonEmptyIndex !== null) {
                $normalizedSubject = mb_strtolower(trim($subject));
                $normalizedFirst = mb_strtolower(trim($cleanLines[$firstNonEmptyIndex]));
    
                if ($normalizedFirst === $normalizedSubject) {
                    unset($cleanLines[$firstNonEmptyIndex]);
                }
            }
        }
    
        $body = implode("\n", $cleanLines);
        $body = preg_replace("/\n{3,}/", "\n\n", $body);
    
        return trim($body);
    }
    
    private function findPart($inbox, int $emailNo, $structure, string $wantSubtype, string $partNumber = '')
    {
        $wantSubtype = strtoupper($wantSubtype);
    
        // Caso: la estructura actual YA es un text/plain o text/html
        if (($structure->type ?? null) === 0 && strtoupper($structure->subtype ?? '') === $wantSubtype) {
            $section = $partNumber !== '' ? $partNumber : '1';
            $data = imap_fetchbody($inbox, $emailNo, $section, FT_PEEK);
    
            if ($data === false || $data === '') {
                $data = imap_body($inbox, $emailNo, FT_PEEK);
            }
    
            return $this->decodePart($data, (int)($structure->encoding ?? 0));
        }
    
        // Recorrer subpartes respetando la numeración IMAP real: 1, 1.1, 1.2, 2, etc.
        if (!empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $currentPartNumber = $partNumber === ''
                    ? (string)($index + 1)
                    : $partNumber . '.' . ($index + 1);
    
                $result = $this->findPart($inbox, $emailNo, $part, $wantSubtype, $currentPartNumber);
                if ($result !== null && trim($result) !== '') {
                    return $result;
                }
            }
        }
    
        return null;
    }
    
    private function decodePart(string $data, int $encoding): string
    {
        switch ($encoding) {
            case 3:
                return base64_decode($data);
            case 4:
                return quoted_printable_decode($data);
            default:
                return $data;
        }
    }

    private function alreadyProcessed(int $mailboxId, string $messageId): bool
    {
        return DB::table('ticket_inbound_processed')
            ->where('mailbox_id', $mailboxId)
            ->where('message_id', $messageId)
            ->exists();
    }

    private function markProcessed(int $mailboxId, string $messageId, string $uid): void
    {
        DB::table('ticket_inbound_processed')->insert([
            'mailbox_id'   => $mailboxId,
            'message_id'   => $messageId,
            'imap_uid'     => $uid,
            'processed_at' => now(),
        ]);
    }

    private function finalizeEmail($inbox, int $emailNo, TicketInboundMailbox $mailbox): void
    {
        try {
            imap_setflag_full($inbox, (string) $emailNo, "\\Seen");
    
            if (!empty($mailbox->processed_folder)) {
                @imap_mail_move($inbox, (string) $emailNo, $mailbox->processed_folder);
                @imap_expunge($inbox);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('[finalizeEmail] ' . $e->getMessage(), 0, $e);
        }
    }
    
    private function extractEmailsFromCsv(?string $value): array
    {
        if (empty($value)) {
            return [];
        }
    
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
    
        return collect($matches[0] ?? [])
            ->map(fn($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }
    
    private function resolveVisibleUserIdsFromEmails(?string $emailsCsv): array
    {
        $emails = $this->extractEmailsFromCsv($emailsCsv);
        if (empty($emails)) {
            return [];
        }
    
        return DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
    
    private function sendAssignedTicketEmailById(int $ticketId): void
    {
        $ticket = DB::table('helpdesk_tickets')->where('id', $ticketId)->first();
        if (!$ticket || empty($ticket->user_id)) {
            return;
        }
    
        if (empty(admin_setting('New Helpdesk Ticket Assigned')) || admin_setting('New Helpdesk Ticket Assigned') != true) {
            return;
        }
    
        $assignedUser = User::find($ticket->user_id);
        if (!$assignedUser || empty($assignedUser->email)) {
            return;
        }
    
        $uArr = [
            'ticket_name'         => $assignedUser->name,
            'ticket_id'           => $ticket->ticket_id,
            'ticket_url'          => route('helpdesk.view', [\Illuminate\Support\Facades\Crypt::encrypt($ticket->ticket_id)]),
            'email'               => $assignedUser->email,
            'subject'             => $ticket->subject,
            'status'              => $ticket->status,
            'priority'            => $ticket->priority,
            'assigned_user_name'  => $assignedUser->name,
            'company_name'        => company_setting('company_name', $ticket->created_by),
        ];
    
        EmailTemplate::sendEmailTemplate(
            'New Helpdesk Ticket Assigned',
            [$assignedUser->email],
            $uArr,
            $ticket->created_by
        );
    }    
    
    private function parseAddress(array $arr): array
    {
        $result = [];
    
        foreach ($arr as $a) {
            $email = strtolower(($a->mailbox ?? '') . '@' . ($a->host ?? ''));
            $name  = isset($a->personal) ? imap_utf8($a->personal) : null;
    
            if ($email !== '@') {
                $result[] = [
                    'email' => $email,
                    'name'  => $name ?: null,
                ];
            }
        }
    
        return $result;
    }    
    
    private function parseEmailReceivedAt($header)
    {
        try {
            if (!empty($header->date)) {
                return \Carbon\Carbon::parse($header->date);
            }
    
            if (!empty($header->MailDate)) {
                return \Carbon\Carbon::parse($header->MailDate);
            }
        } catch (\Throwable $e) {
            // fallback abajo
        }
    
        return now();
    }
    
    private function shouldSkipAttachment(array $att): bool
    {
        $filename    = strtolower($att['filename'] ?? '');
        $mimeType    = strtolower($att['mime_type'] ?? '');
        $disposition = strtolower($att['disposition'] ?? '');
        $contentId   = trim((string)($att['content_id'] ?? ''));
        $size        = (int)($att['size'] ?? 0);
    
        $isImage = str_starts_with($mimeType, 'image/');
        $isInline = $disposition === 'inline';
        $hasContentId = $contentId !== '';
    
        $looksLikeSignatureName =
            preg_match('/^(image\d+|logo|firma|signature|correo|mailimage|outlook-\w+)\.(png|jpg|jpeg|gif|bmp|webp)$/i', $filename);
    
        $isTinyImage = $isImage && $size > 0 && $size < 100000;
    
        return $isImage && (
            $isInline ||
            $hasContentId ||
            $looksLikeSignatureName ||
            $isTinyImage
        );
    }
    
    private function resolveRealSender($inbox, int $emailNo, $header, ?string $rawHeader = null): array
    {
        try {
            $rawHeader = $rawHeader ?: imap_fetchheader($inbox, $emailNo);
    
            // 1. Preferir X-Original-From
            $original = $this->extractAddressFromRawHeader($rawHeader, 'X-Original-From');
            if (!empty($original['email'])) {
                return $original;
            }
    
            // 2. Luego Reply-To
            $replyToParsed = $this->parseAddress($header->reply_to ?? []);
            if (!empty($replyToParsed[0]['email'])) {
                return [
                    'email' => $replyToParsed[0]['email'],
                    'name'  => $replyToParsed[0]['name'] ?? null,
                ];
            }
    
            // 3. Fallback a From normal
            $fromParsed = $this->parseAddress($header->from ?? []);
            if (!empty($fromParsed[0]['email'])) {
                return [
                    'email' => $fromParsed[0]['email'],
                    'name'  => $fromParsed[0]['name'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('resolveRealSender fallback', [
                'error' => $e->getMessage(),
            ]);
        }
    
        return [
            'email' => null,
            'name'  => null,
        ];
    }
    
    private function extractAddressFromRawHeader(string $rawHeader, string $headerName): array
    {
        $pattern = '/^' . preg_quote($headerName, '/') . ':\s*(.+)$/mi';
    
        if (!preg_match($pattern, $rawHeader, $m)) {
            return ['email' => null, 'name' => null];
        }
    
        $value = trim($m[1]);
    
        // Caso: "Nombre Apellido <correo@dominio.com>"
        if (preg_match('/^(.*)<\s*([^>]+)\s*>$/', $value, $parts)) {
            $name = trim(trim($parts[1]), '"\' ');
            $email = strtolower(trim($parts[2]));
    
            return [
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'name'  => $name !== '' ? imap_utf8($name) : null,
            ];
        }
    
        // Caso: solo viene el email
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => strtolower($value),
                'name'  => null,
            ];
        }
    
        // Buscar cualquier email dentro del texto
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $emailMatch)) {
            return [
                'email' => strtolower(trim($emailMatch[0])),
                'name'  => null,
            ];
        }
    
        return ['email' => null, 'name' => null];
    } 
    
    private function appendHelpdeskReply(object $ticket, array $msg, array $attachments = []): int
    {
        try {
            $fromEmail = strtolower(trim((string)($msg['from_email'] ?? '')));
    
            // Buscar usuario
            $userId = null;
            if ($fromEmail !== '') {
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [$fromEmail])
                    ->value('id');
            }
    
            // Si no existe → superadmin
            $sender = $userId ?: 1;
    
            // 🔥 Aquí está la magia: limpiar solo lo nuevo
            $cleanReply = $this->extractNewReplyBody($msg['body'], $msg['subject']);
    
            if ($cleanReply === '') {
                $cleanReply = '[Reply sin contenido nuevo]';
            }
    
            $attachmentJson = json_encode($attachments ?: [], JSON_UNESCAPED_SLASHES);
            
            $receivedAt = !empty($msg['received_at'])
                ? Carbon::parse($msg['received_at'])
                    ->setTimezone('America/Puerto_Rico')
                : now('America/Puerto_Rico');
    
            $replyId = DB::table('helpdesk_conversions')->insertGetId([
                'ticket_id'   => $ticket->id,
                'description' => nl2br(e($fullDescription)),
                'attachments' => $attachmentJson,
                'sender'      => $sender,
                'created_at'  => $receivedAt ?? now(),
                'updated_at'  => now(),
            ]);
    
            // actualizar ticket
            DB::table('helpdesk_tickets')
                ->where('id', $ticket->id)
                ->update([
                    'updated_at' => $receivedAt ?? now()
                ]);
    
            return $replyId;
    
        } catch (\Throwable $e) {
            throw new \RuntimeException('[appendHelpdeskReply] ' . $e->getMessage(), 0, $e);
        }
    }
    
    private function extractNewReplyBody(string $body, ?string $subject = null): string
    {
        $body = $this->cleanEmailBody($body, $subject);
    
        $patterns = [
            '/\nOn .+?wrote:\s*$/is',
            '/\nEl .+?escribi[oó]:\s*$/is',
            '/\nFrom:\s.+$/is',
            '/\nDe:\s.+$/is',
            '/\n-+\s*Original Message\s*-+$/is',
            '/\n-+\s*Mensaje original\s*-+$/is',
        ];
    
        foreach ($patterns as $pattern) {
            $parts = preg_split($pattern, $body, 2);
            if (!empty($parts[0]) && count($parts) > 1) {
                $body = $parts[0];
                break;
            }
        }
    
        return trim($body);
    }    
    
    private function findExistingHelpdeskTicketFromHeaders(array $msg)
    {
        $inReplyTo = trim((string)($msg['in_reply_to'] ?? ''));
        $references = trim((string)($msg['references'] ?? ''));
    
        if ($inReplyTo !== '') {
            $row = DB::table('helpdesk_email_messages')
                ->select('ticket_id')
                ->where('message_id', $inReplyTo)
                ->first();
    
            if ($row) {
                return DB::table('helpdesk_tickets')->where('id', $row->ticket_id)->first();
            }
        }
    
        $referenceIds = $this->extractMessageIdsFromText($references);
    
        if (!empty($referenceIds)) {
            $row = DB::table('helpdesk_email_messages')
                ->select('ticket_id')
                ->whereIn('message_id', $referenceIds)
                ->orderByDesc('id')
                ->first();
    
            if ($row) {
                return DB::table('helpdesk_tickets')->where('id', $row->ticket_id)->first();
            }
        }
    
        return null;
    }    
    
    private function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);
    
        $subject = preg_replace('/^((re|fw|fwd|rv)\s*:\s*)+/i', '', $subject);
        $subject = preg_replace('/\s+/', ' ', $subject);
    
        return mb_strtolower(trim($subject));
    }

    private function extractHeaderValue(string $rawHeader, string $headerName): ?string
    {
        $pattern = '/^' . preg_quote($headerName, '/') . ':\s*(.+(?:\r?\n[ \t].+)*)$/mi';
    
        if (!preg_match($pattern, $rawHeader, $m)) {
            return null;
        }
    
        // Unfold header multiline
        $value = preg_replace("/\r?\n[ \t]+/", ' ', trim($m[1]));
    
        return trim($value);
    }
    
    private function extractMessageIdsFromText(?string $text): array
    {
        if (empty($text)) {
            return [];
        }
    
        preg_match_all('/<[^>]+>/', $text, $matches);
    
        return collect($matches[0] ?? [])
            ->map(fn($v) => trim($v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    } 
    
    private function storeHelpdeskEmailMessage(
        int $ticketId,
        ?int $conversionId,
        ?int $mailboxId,
        array $msg,
        string $direction = 'inbound'
    ): void {
        $messageId = trim((string)($msg['message_id'] ?? ''));
    
        if ($messageId === '') {
            return;
        }
    
        $exists = DB::table('helpdesk_email_messages')
            ->where('message_id', $messageId)
            ->exists();
    
        if ($exists) {
            return;
        }

        $receivedAt = !empty($msg['received_at'])
            ? Carbon::parse($msg['received_at'])
                ->setTimezone('America/Puerto_Rico')
            : now('America/Puerto_Rico');        
    
        DB::table('helpdesk_email_messages')->insert([
            'ticket_id'       => $ticketId,
            'conversion_id'   => $conversionId,
            'mailbox_id'      => $mailboxId,
            'message_id'      => $messageId,
            'in_reply_to'     => $msg['in_reply_to'] ?? null,
            'references_text' => $msg['references'] ?? null,
            'subject'         => $msg['subject'] ?? null,
            'from_email'      => $msg['from_email'] ?? null,
            'direction'       => $direction,
            'created_at'      => $receivedAt ?? now(),
            'updated_at'      => now(),
        ]);
    }

    private function findExistingHelpdeskTicketByReply(array $msg)
    {
        $incomingSubject = trim((string)($msg['subject'] ?? ''));
    
        if ($incomingSubject === '') {
            return null;
        }
    
        $normalizedIncoming = $this->normalizeSubject($incomingSubject);
    
        if ($normalizedIncoming === '') {
            return null;
        }
    
        $tickets = DB::table('helpdesk_tickets')
            ->select('id', 'ticket_id', 'subject', 'workspace', 'created_by')
            ->whereNotNull('subject')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    
        foreach ($tickets as $ticket) {
            $normalizedTicket = $this->normalizeSubject((string) $ticket->subject);
    
            if ($normalizedTicket !== '' && $normalizedTicket === $normalizedIncoming) {
                return $ticket;
            }
        }
    
        return null;
    }    
    
    private function stringifyAddressList($value): string
    {
        if (empty($value)) {
            return '';
        }
    
        if (is_string($value)) {
            return trim($value);
        }
    
        if (is_array($value)) {
            return collect($value)
                ->map(function ($item) {
                    if (is_string($item)) {
                        return trim($item);
                    }
    
                    if (is_array($item)) {
                        $name  = trim((string) ($item['name'] ?? ''));
                        $email = trim((string) ($item['email'] ?? ''));
    
                        if ($name !== '' && $email !== '') {
                            return $name . ' <' . $email . '>';
                        }
    
                        return $email !== '' ? $email : $name;
                    }
    
                    return '';
                })
                ->filter()
                ->implode(', ');
        }
    
        return trim((string) $value);
    }

    private function buildInboundDescriptionHtml(array $msg, string $cleanBodyHtml): string
    {
        $headerLines = [];
    
        $toLine = $this->stringifyAddressList(
            $msg['to'] ?? $msg['to_raw'] ?? $msg['to_email'] ?? null
        );
    
        $ccLine = $this->stringifyAddressList(
            $msg['cc'] ?? $msg['cc_raw'] ?? null
        );
    
        if ($toLine !== '') {
            $headerLines[] = '<p><strong>To:</strong> ' . e($toLine) . '</p>';
        }
    
        if ($ccLine !== '') {
            $headerLines[] = '<p><strong>Cc:</strong> ' . e($ccLine) . '</p>';
        }
    
        $headerHtml = implode('', $headerLines);
    
        if ($headerHtml !== '') {
            $headerHtml .= '<br>';
        }
    
        return $headerHtml . $cleanBodyHtml;
    }
    
    private function sanitizeInboundHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
    
        // Quitar scripts, iframes, objects, embeds, forms, etc.
        $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $html);
        $html = preg_replace('#<(iframe|object|embed|form|input|button|textarea|select|meta|link)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(iframe|object|embed|form|input|button|textarea|select|meta|link)\b[^>]*?/?>#is', '', $html);
    
        // Permitir solo etiquetas básicas útiles para emails
        $allowedTags = '<p><br><b><strong><i><em><u><ul><ol><li><a><blockquote><div><span>';
    
        $html = strip_tags($html, $allowedTags);
    
        // Eliminar atributos on* tipo onclick, onload, etc.
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
    
        // Eliminar estilos inline
        $html = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+style\s*=\s*'[^']*'/i", '', $html);
    
        // Neutralizar javascript: y data: en href
        $html = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace("/href\s*=\s*'javascript:[^']*'/i", "href='#'", $html);
        $html = preg_replace('/href\s*=\s*"data:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace("/href\s*=\s*'data:[^']*'/i", "href='#'", $html);
    
        // Dejar solo href en anchors
        $html = preg_replace_callback('/<a\b([^>]*)>/i', function ($matches) {
            $attrs = $matches[1] ?? '';
    
            if (preg_match('/href\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', $attrs, $hrefMatch)) {
                return '<a ' . $hrefMatch[0] . '>';
            }
    
            return '<a>';
        }, $html);
    
        // Limpiar html vacío repetido
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
        $html = preg_replace('/(<p>\s*<\/p>\s*){2,}/i', '', $html);
    
        return trim($html);
    }    
    
    private function normalizeInboundHtml(string $html): string
    {
        $html = $this->decodeHtmlPart($html);
    
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $html);
    
        // elimina imágenes inline / firmas
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);
    
        // quitar atributos peligrosos
        $html = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);
    
        // quitar styles inline
        $html = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s+style\s*=\s*'[^']*'/i", '', $html);
    
        // neutralizar href peligrosos
        $html = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace("/href\s*=\s*'javascript:[^']*'/i", "href='#'", $html);
        $html = preg_replace('/href\s*=\s*"data:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace("/href\s*=\s*'data:[^']*'/i", "href='#'", $html);
    
        // permitir solo tags útiles de correo
        $allowedTags = '<p><br><b><strong><i><em><u><ul><ol><li><a><blockquote><div><span>';
    
        $html = strip_tags($html, $allowedTags);
    
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
        $html = preg_replace('/(<p>\s*<\/p>\s*){2,}/i', '', $html);
    
        return trim($html);
    }  
    
    private function removeNoiseLinesFromHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
    
        // Quitar la línea/frase de unsubscribe aunque venga mezclada con tags
        $html = preg_replace(
            '/To unsubscribe from this group and stop receiving emails from it, send an email.*?(<br\s*\/?>|<\/p>|<\/div>|<\/li>|$)/is',
            '',
            $html
        );
    
        // Si por alguna razón quedó la versión sin coma o ligeramente distinta
        $html = preg_replace(
            '/To unsubscribe from this group and stop receiving emails from it.*?(<br\s*\/?>|<\/p>|<\/div>|<\/li>|$)/is',
            '',
            $html
        );
    
        // Limpiar bloques vacíos que queden después
        $html = preg_replace('/<p>\s*(<br\s*\/?>)?\s*<\/p>/i', '', $html);
        $html = preg_replace('/<div>\s*(<br\s*\/?>)?\s*<\/div>/i', '', $html);
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html);
    
        return trim($html);
    } 
    
    private function deleteSavedAttachments(array $savedAttachments): void
    {
        $folders = [];
    
        foreach ($savedAttachments as $file) {
            $path = base_path($file['path'] ?? '');
    
            if ($path && file_exists($path) && is_file($path)) {
                @unlink($path);
                $folders[] = dirname($path);
            }
        }
    
        foreach (array_unique($folders) as $folder) {
            if (is_dir($folder)) {
                $files = @scandir($folder);
                if ($files && count(array_diff($files, ['.', '..'])) === 0) {
                    @rmdir($folder);
                }
            }
        }
    }
}