<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailToTask extends Command
{
    protected $signature = 'email:to-task';
    protected $description = 'Lee correos via IMAP y crea tareas en proyectos de Dash';

    public function handle()
    {
        $mailbox = sprintf('{%s:%s/imap/%s}%s',
            env('EMAIL_TASK_HOST'),
            env('EMAIL_TASK_PORT', 993),
            env('EMAIL_TASK_ENCRYPTION','ssl'),
            env('EMAIL_TASK_FOLDER','INBOX')
        );

        $user = env('EMAIL_TASK_USERNAME');
        $pass = env('EMAIL_TASK_PASSWORD');

        $inbox = @imap_open($mailbox, $user, $pass);
        if(!$inbox){
            $this->error('No se pudo abrir IMAP: '.imap_last_error());
            return 1;
        }

        $emails = imap_search($inbox, 'UNSEEN') ?: [];
        $this->info('Correos nuevos: '.count($emails));

        // Mapeos (ajusta a tu base): alias de usuario → user_id
        $userMap = [
            'luis' => 1,
            'admin' => 1,
            // 'maria' => 7,
        ];

        foreach($emails as $num){
            try {
                $header  = imap_headerinfo($inbox, $num);
                $from    = strtolower($header->from[0]->mailbox.'@'.$header->from[0]->host);
                $subject = $this->decodeMimeStr($header->subject ?? '(sin asunto)');
                $datetime= Carbon::parse($header->date ?? now());
                $allow   = env('EMAIL_TASK_FROM_ALLOW','*');

                if($allow !== '*' && ! collect(explode(',', $allow))->map(fn($s)=>trim(strtolower($s)))->contains($from)){
                    $this->warn("Remitente no permitido: $from");
                    $this->moveOrFlag($inbox, $num, false);
                    continue;
                }

                // Parsear asunto
                $meta = $this->parseSubject($subject);
                $title = $meta['title'];
                $projectId = $meta['project_id'] ?? (int) env('EMAIL_TASK_DEFAULT_PROJECT', 0);
                $priority  = $meta['priority'] ?? 'medium';
                $due       = isset($meta['due']) ? Carbon::parse($meta['due']) : null;
                $assignee  = isset($meta['assignee']) ? ($userMap[$meta['assignee']] ?? null) : null;

                // Cuerpo y adjuntos
                [$bodyText, $attachments] = $this->extractBodyAndAttachments($inbox, $num);

                // === Crea la tarea en Dash ===
                // Ajusta a tus modelos/servicios reales de Dash.
                // Ejemplo genérico con DB (si no tienes el servicio a mano):
                DB::beginTransaction();
                try {
                    // Tabla y campos de ejemplo. AJUSTA a tu esquema real:
                    $taskId = DB::table('tasks')->insertGetId([
                        'uuid'        => (string) Str::uuid(),
                        'project_id'  => $projectId,
                        'title'       => $title,
                        'description' => $bodyText,
                        'priority'    => $priority,   // o priority_id si usas catálogo
                        'due_date'    => $due?->toDateString(),
                        'assigned_to' => $assignee,
                        'status'      => 'todo',      // ajusta a tu flujo
                        'created_by'  => 1,           // id del usuario "sistema" o quien corresponda
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    // Guardar adjuntos (si tu esquema usa otra tabla, ajusta)
                    foreach ($attachments as $att) {
                        DB::table('task_attachments')->insert([
                            'task_id'    => $taskId,
                            'filename'   => $att['filename'],
                            'path'       => $att['path'],   // storage path
                            'mime'       => $att['mime'],
                            'size'       => $att['size'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::commit();
                    $this->info("Tarea creada #$taskId :: $title");

                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }

                // Mover a Processed
                $this->moveOrFlag($inbox, $num, true);

            } catch (\Throwable $e) {
                $this->error('Error con un correo: '.$e->getMessage());
                $this->moveOrFlag($inbox, $num, false);
            }
        }

        imap_close($inbox, CL_EXPUNGE);
        return 0;
    }

    private function moveOrFlag($inbox, $num, $ok)
    {
        $folder = $ok ? env('EMAIL_TASK_MOVE_PROCESSED') : env('EMAIL_TASK_MOVE_FAILED');
        if ($folder) {
            @imap_mail_move($inbox, (string)$num, $folder);
            @imap_expunge($inbox);
        } else {
            // marcar como SEEN para no volver a procesar
            @imap_setflag_full($inbox, (string)$num, "\\Seen");
        }
    }

    private function decodeMimeStr($str)
    {
        $result = '';
        foreach (imap_mime_header_decode($str) as $part) {
            $charset = strtoupper($part->charset);
            $text = $part->text;
            if ($charset !== 'UTF-8' && $charset !== 'DEFAULT') {
                $text = @iconv($charset, 'UTF-8//TRANSLIT', $text) ?: $text;
            }
            $result .= $text;
        }
        return trim($result);
    }

    private function parseSubject($subject)
    {
        $meta = ['title' => $subject];

        // PROJ
        if (preg_match('/\[PROJ:([^\]]+)\]/i', $subject, $m)) {
            $meta['project_id'] = is_numeric($m[1]) ? (int)$m[1] : null;
        }
        // ASSIGN
        if (preg_match('/\[ASSIGN:([^\]]+)\]/i', $subject, $m)) {
            $meta['assignee'] = strtolower(trim($m[1]));
        }
        // PRIO
        if (preg_match('/\[PRIO:(low|medium|high)\]/i', $subject, $m)) {
            $meta['priority'] = strtolower($m[1]);
        }
        // DUE
        if (preg_match('/\[DUE:([0-9\-\/]+)\]/i', $subject, $m)) {
            $meta['due'] = str_replace('/', '-', $m[1]);
        }
        // Título limpio
        $meta['title'] = trim(preg_replace('/\[[^\]]+\]/', '', $subject)) ?: 'Nueva tarea por email';

        return $meta;
    }

    private function extractBodyAndAttachments($inbox, $num)
    {
        $structure = imap_fetchstructure($inbox, $num);
        $bodyText = '';
        $attachments = [];

        if (!isset($structure->parts)) {
            $bodyText = $this->getPart($inbox, $num, 1, $structure->encoding);
        } else {
            $index = 1;
            foreach ($structure->parts as $partNo => $part) {
                $section = (string)($partNo + 1);

                // Texto
                if ($part->type == TYPETEXT && in_array(strtolower($part->subtype ?? ''), ['plain','html'])) {
                    $chunk = $this->getPart($inbox, $num, $section, $part->encoding);
                    if (strtolower($part->subtype) === 'html') {
                        // convertir html a texto simple
                        $chunk = trim(preg_replace('/\s+/', ' ', strip_tags($chunk)));
                    }
                    $bodyText .= "\n".$chunk;
                }

                // Adjuntos
                $isAttachment = false;
                $filename = null;
                if (isset($part->dparameters)) {
                    foreach ($part->dparameters as $obj) {
                        if (strtolower($obj->attribute) == 'filename') {
                            $isAttachment = true;
                            $filename = $obj->value;
                        }
                    }
                }
                if (isset($part->parameters)) {
                    foreach ($part->parameters as $obj) {
                        if (strtolower($obj->attribute) == 'name') {
                            $isAttachment = true;
                            $filename = $filename ?: $obj->value;
                        }
                    }
                }
                if ($isAttachment) {
                    $raw = imap_fetchbody($inbox, $num, $section);
                    $data = $this->decodePart($raw, $part->encoding);
                    $safeName = $this->sanitizeFilename($filename ?: ('file-'.$section));
                    $path = 'tasks/tmp/'.date('Ymd').'/'.Str::uuid().'_'.$safeName;
                    Storage::disk('public')->put($path, $data);
                    $attachments[] = [
                        'filename' => $safeName,
                        'path'     => 'public/'.$path,
                        'mime'     => ($part->subtype ?? 'octet-stream'),
                        'size'     => strlen($data),
                    ];
                }

                $index++;
            }
        }

        $bodyText = trim($bodyText);
        return [$bodyText ?: '(sin contenido)', $attachments];
    }

    private function getPart($inbox, $num, $section, $encoding)
    {
        $data = imap_fetchbody($inbox, $num, $section);
        return $this->decodePart($data, $encoding);
    }

    private function decodePart($data, $encoding)
    {
        switch ((int)$encoding) {
            case ENCBASE64: return base64_decode($data);
            case ENCQUOTEDPRINTABLE: return quoted_printable_decode($data);
            default: return $data;
        }
    }

    private function sanitizeFilename($name)
    {
        $name = preg_replace('/[^\w\.\-\s]/u', '_', $name);
        return mb_substr($name, 0, 120);
    }
}
