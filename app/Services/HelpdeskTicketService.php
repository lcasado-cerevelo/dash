<?php

namespace App\Services;

use App\Models\HelpdeskConversion;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HelpdeskTicketService
{
    public static function normalizeIds($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $value = explode(',', $value);
            }
        }

        return collect((array) $value)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function encodeIds(array $ids): ?string
    {
        $ids = self::normalizeIds($ids);
        return empty($ids) ? null : json_encode($ids);
    }

    public static function mergeIds(...$lists): array
    {
        return collect($lists)
            ->flatten(1)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function addTouchedUser(HelpdeskTicket $ticket, int $userId): void
    {
        $current = self::normalizeIds($ticket->touched_by_user_ids);
        $merged  = self::mergeIds($current, [$userId]);
        $ticket->touched_by_user_ids = self::encodeIds($merged);
        $ticket->save();
    }

    public static function addVisibleUsers(HelpdeskTicket $ticket, array $userIds): void
    {
        $current = self::normalizeIds($ticket->visible_to_user_ids);
        $merged  = self::mergeIds($current, $userIds);
        $ticket->visible_to_user_ids = self::encodeIds($merged);
        $ticket->save();
    }

    public static function audit(HelpdeskTicket $ticket, string $field, $oldValue, $newValue, ?int $changedBy = null): void
    {
        if ((string) $oldValue === (string) $newValue) {
            return;
        }

        $changedBy = $changedBy ?: (Auth::id() ?: 0);

        HelpdeskTicketAudit::create([
            'ticket_id'   => $ticket->id,
            'changed_by'  => $changedBy,
            'field_name'  => $field,
            'old_value'   => is_null($oldValue) ? null : (string) $oldValue,
            'new_value'   => is_null($newValue) ? null : (string) $newValue,
        ]);

        if ($changedBy > 0) {
            self::addTouchedUser($ticket, $changedBy);
        }
    }

    public static function createSystemReply(HelpdeskTicket $ticket, string $message, ?int $senderId = null): void
    {
        HelpdeskConversion::create([
            'sender'      => $senderId ?: (Auth::id() ?: 'system'),
            'ticket_id'   => $ticket->id,
            'description' => $message,
            'attachments' => json_encode([]),
        ]);
    }

    public static function userIsViewOnlyForTicket(HelpdeskTicket $ticket, int $userId): bool
    {
        $visible = self::normalizeIds($ticket->visible_to_user_ids);
        return in_array($userId, $visible, true) && (int) $ticket->user_id !== $userId;
    }
    
    private function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);
        $subject = html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
        // quita RE:, FW:, FWD:, RV: repetidos
        $subject = preg_replace('/^((re|fw|fwd|rv)\s*:\s*)+/i', '', $subject);
    
        // normaliza espacios
        $subject = preg_replace('/\s+/', ' ', $subject);
    
        return mb_strtolower(trim($subject));
    }

    private function findExistingHelpdeskTicketByReply(array $msg)
    {
        $incomingSubject = trim((string)($msg['subject'] ?? ''));
    
        if ($incomingSubject === '') {
            return null;
        }
    
        // solo intenta si parece reply/forward o si quieres que también matchee por subject limpio
        $normalizedIncoming = $this->normalizeSubject($incomingSubject);
    
        if ($normalizedIncoming === '') {
            return null;
        }
    
        $tickets = DB::table('helpdesk_tickets')
            ->select('id', 'ticket_id', 'subject', 'workspace', 'created_by', 'touched_by_user_ids')
            ->whereNotNull('subject')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    
        foreach ($tickets as $ticket) {
            $normalizedTicket = $this->normalizeSubject((string)$ticket->subject);
    
            if ($normalizedTicket !== '' && $normalizedTicket === $normalizedIncoming) {
                return $ticket;
            }
        }
    
        return null;
    }
    
    private function appendHelpdeskReply(object $ticket, array $msg, array $savedAttachments = []): int
    {
        try {
            $fromEmail = strtolower(trim((string)($msg['from_email'] ?? '')));
    
            $userId = null;
            if ($fromEmail !== '') {
                $userId = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [$fromEmail])
                    ->value('id');
            }
    
            // si no existe el email en users -> superadmin id 1
            $sender = $userId ?: 1;
    
            $cleanReply = $this->extractNewReplyBody(
                (string)($msg['body'] ?? ''),
                (string)($msg['subject'] ?? '')
            );
    
            if ($cleanReply === '') {
                $cleanReply = '[Reply inbound sin texto nuevo detectable]';
            }
    
            $attachmentJson = json_encode($savedAttachments ?: [], JSON_UNESCAPED_SLASHES);
    
            $replyId = DB::table('helpdesk_conversions')->insertGetId([
                'ticket_id'   => $ticket->id,
                'description' => nl2br(e($cleanReply)),
                'attachments' => $attachmentJson,
                'sender'      => $sender,
                'created_at'  => $msg['received_at'] ?? now(),
                'updated_at'  => $msg['received_at'] ?? now(),
            ]);
    
            // actualizar touched_by_user_ids y updated_at del ticket
            $currentTouched = DB::table('helpdesk_tickets')
                ->where('id', $ticket->id)
                ->value('touched_by_user_ids');
    
            $decodedTouched = [];
            if (!empty($currentTouched)) {
                $decodedTouched = json_decode($currentTouched, true) ?: [];
            }
    
            $mergedTouched = HelpdeskTicketService::mergeIds($decodedTouched, [$sender]);
    
            DB::table('helpdesk_tickets')
                ->where('id', $ticket->id)
                ->update([
                    'touched_by_user_ids' => HelpdeskTicketService::encodeIds($mergedTouched),
                    'updated_at' => $msg['received_at'] ?? now(),
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
    
        $body = preg_replace("/\n{3,}/", "\n\n", trim($body));
    
        return trim($body);
    }
    
}