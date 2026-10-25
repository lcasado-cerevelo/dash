<?php

namespace App\Console\Commands;

use App\Http\Controllers\HelpdeskTicketController;
use App\Mail\CommonEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

/**
 * Vista previa de los correos que el sistema envía al crear, asignar o responder un ticket.
 * Arma el correo igual que el controlador y lo manda por Brevo SOLO a la dirección de prueba;
 * nunca al destinatario real.
 */
class TicketPreviewMail extends Command
{
    protected $signature = 'tickets:preview-mail
        {type : created | assigned | reply}
        {--ticket= : Número del ticket (el que se ve en pantalla). Por defecto, el más reciente}
        {--to= : Dirección que recibe la prueba (por defecto TICKET_NOTIFY_TEST_TO)}';

    protected $description = 'Envía solo a una dirección de prueba cómo se ve el correo de creación, asignación o respuesta de un ticket';

    public function handle(TicketNotificationService $svc): int
    {
        $to = $this->option('to') ?: config('ticket_notify.test_to');
        if (!$to) {
            $this->error('Defina TICKET_NOTIFY_TEST_TO en el .env o use --to=. Este comando nunca envía al destinatario real.');
            return self::FAILURE;
        }
        $svc->forceTo($to);

        $type = $this->argument('type');
        if (!in_array($type, ['created', 'assigned', 'reply'], true)) {
            $this->error('type debe ser created, assigned o reply.');
            return self::FAILURE;
        }

        $ticket = $this->option('ticket')
            ? HelpdeskTicket::where('ticket_id', ltrim((string) $this->option('ticket'), '#'))->first()
            : HelpdeskTicket::orderByDesc('id')->first();
        if (!$ticket) {
            $this->error('No se encontró el ticket.');
            return self::FAILURE;
        }

        // Llama a los métodos privados del controlador para que el correo sea idéntico al real.
        $ctl  = app(HelpdeskTicketController::class);
        $call = function (string $method, ...$args) use ($ctl) {
            return (fn () => $this->$method(...$args))->call($ctl);
        };

        $url     = route('helpdesk.view', [Crypt::encrypt($ticket->ticket_id)]);
        $creator = $ticket->created_by ? User::find($ticket->created_by) : null;

        if ($type === 'created') {
            $templateName = 'New Helpdesk Ticket';
            $recipient    = $ticket->email;
            $uArr = [
                'ticket_name'       => $ticket->name,
                'email'             => $ticket->email,
                'ticket_id'         => $ticket->ticket_id,
                'ticket_url'        => $url,
                'subject'           => $ticket->subject,
                'status'            => $ticket->status,
                'priority'          => $call('priorityLabel', $ticket->priority, 'es'),
                'created_user_name' => optional($creator)->name,
                'company_name'      => company_setting('company_name', $ticket->user_id),
            ];
        } elseif ($type === 'assigned') {
            $assigned = User::find($ticket->user_id);
            if (!$assigned) {
                $this->error('El ticket no tiene asignado.');
                return self::FAILURE;
            }
            $lang      = substr((string) ($assigned->lang ?: 'es'), 0, 2);
            $createdBy = optional($creator)->name ?: ($ticket->name ?: ($ticket->email ?: '-'));
            $templateName = 'New Helpdesk Ticket Assigned';
            $recipient    = $assigned->email;
            $uArr = [
                'ticket_name'        => $assigned->name,
                'ticket_id'          => $ticket->ticket_id,
                'ticket_url'         => $url,
                'email'              => $assigned->email,
                'subject'            => $ticket->subject,
                'status'             => $ticket->status,
                'priority'           => $call('priorityLabel', $ticket->priority, $lang),
                'description'        => trim((string) $ticket->description) !== '' ? $ticket->description : '-',
                'assignment_comment' => $call('emailNoteBlock', $lang === 'en' ? 'Assignment note' : 'Comentario de asignación', 'Comentario de ejemplo de la asignación', false, '#eef1ff'),
                'assigned_user_name' => $assigned->name,
                'created_user_name'  => $createdBy,
                'company_name'       => company_setting('company_name', $ticket->created_by),
            ];
        } else {
            $templateName = 'New Helpdesk Ticket Reply';
            $recipient    = $ticket->email ?: optional($creator)->email;
            $uArr = [
                'ticket_name'   => $ticket->name ?: $ticket->email,
                'ticket_id'     => $ticket->ticket_id,
                'ticket_url'    => $url,
                'email'         => $recipient,
                'reply_message' => $call('emailNoteBlock', 'Mensaje', '<p>Texto de respuesta de ejemplo para ver cómo se muestra.</p>', true),
                'subject'       => $ticket->subject,
                'status'        => $ticket->status,
                'priority'      => $call('priorityLabel', $ticket->priority, 'es'),
                'company_name'  => company_setting('company_name', $ticket->created_by),
            ];
        }

        // Mismo armado que EmailTemplate::sendEmailTemplate().
        $template = EmailTemplate::where('name', $templateName)->first();
        if (!$template) {
            $this->error("No existe la plantilla '{$templateName}'.");
            return self::FAILURE;
        }

        $renderUser = $creator ?: User::find($ticket->user_id);
        $lang       = !empty($renderUser->lang) ? $renderUser->lang : 'en';

        $content = EmailTemplateLang::where('parent_id', $template->id)->where('lang', 'LIKE', $lang)->first()
            ?: EmailTemplateLang::where('parent_id', $template->id)->where('lang', 'LIKE', 'en')->first();
        if (!$content) {
            $this->error('La plantilla no tiene contenido para el idioma.');
            return self::FAILURE;
        }

        $content->from    = $template->from;
        $content->subject = !empty($content->subject) ? EmailTemplate::replaceVariable($content->subject, $uArr) : $template->name;
        $content->content = !empty($content->content) ? EmailTemplate::replaceVariable($content->content, $uArr) : '';

        $workspace = $renderUser->workspace_id ?? $template->workspace_id ?? null;

        try {
            $html = (new CommonEmailTemplate($content, $renderUser->id, $workspace))->render();
        } catch (\Throwable $e) {
            $this->error('No se pudo armar el correo: ' . $e->getMessage());
            return self::FAILURE;
        }

        $result = $svc->send([(string) $recipient], [], $content->subject, $html, $workspace ? (int) $workspace : null);

        if ($result['is_success']) {
            $this->info("Vista previa '{$templateName}' (ticket #{$ticket->ticket_id}) enviada a {$to} [destinatario real: {$recipient}]");
            return self::SUCCESS;
        }

        $this->error('FALLÓ: ' . $result['error']);
        return self::FAILURE;
    }
}
