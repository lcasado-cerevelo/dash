<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;

/**
 * FixHelpdeskEmailTemplatesSeeder
 * --------------------------------------------------------------------------
 * Reescribe el CONTENIDO de las plantillas de correo del Helpdesk para que
 * cada situacion (creacion, asignacion, respuesta/cierre) envie un correo
 * claro y con la informacion del ticket.
 *
 * El contenido de los correos NO esta en el codigo: vive en la base de datos
 * (tablas `email_templates` y `email_template_langs`). El SaaS original traia
 * un texto generico ("Welcome to {app_name}" / "Validating your ...") que es
 * lo que estaban recibiendo las personas.
 *
 * Este seeder es IDEMPOTENTE (usa updateOrCreate): se puede correr las veces
 * que haga falta sin duplicar nada.
 *
 *   php artisan db:seed --class=FixHelpdeskEmailTemplatesSeeder
 *
 * Idiomas incluidos: 'es' (principal) y 'en' (fallback que usa el sistema
 * cuando el usuario no tiene idioma o su idioma no existe).
 *
 * Variables disponibles por plantilla (las que el controlador realmente pasa):
 *   - New Helpdesk Ticket          : {ticket_id} {subject} {status} {priority}
 *                                     {ticket_url} {email} {created_user_name}
 *                                     {company_name}
 *     (OJO: {subject}, {status}, {priority} y {created_user_name} solo salen
 *      si tambien aplicas el parche del HelpdeskTicketController. Sin el parche
 *      esas saldran como "-".)
 *   - New Helpdesk Ticket Assigned : {assigned_user_name} {ticket_id} {subject}
 *                                     {status} {priority} {ticket_url} {email}
 *                                     {created_user_name} {company_name}
 *   - New Helpdesk Ticket Reply    : {ticket_name} {ticket_id} {subject}
 *                                     {status} {priority} {reply_description}
 *                                     {ticket_url} {email} {company_name}
 * --------------------------------------------------------------------------
 */
class FixHelpdeskEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'New Helpdesk Ticket'          => $this->newTicketTemplate(),
            'New Helpdesk Ticket Assigned' => $this->assignedTemplate(),
            'New Helpdesk Ticket Reply'    => $this->replyTemplate(),
        ];

        foreach ($templates as $name => $def) {
            // 1) Cabecera de la plantilla. Si no existe (caso tipico de
            //    "New Helpdesk Ticket Assigned"), se crea.
            $template = EmailTemplate::where('name', $name)->first();

            if (!$template) {
                $template = EmailTemplate::create([
                    'name'         => $name,
                    'from'         => 'Cerevelo',
                    'module_name'  => 'general',
                    'created_by'   => 1,
                    'workspace_id' => 0,
                ]);
            }

            // 2) Contenido por idioma (es + en). Se sobreescribe el existente.
            foreach ($def['lang'] as $lang => $content) {
                EmailTemplateLang::updateOrCreate(
                    [
                        'parent_id' => $template->id,
                        'lang'      => $lang,
                    ],
                    [
                        'subject'     => $def['subject'][$lang] ?? $name,
                        'content'     => $content,
                        'variables'   => $def['variables'],
                        'module_name' => 'general',
                    ]
                );
            }

            $this->command?->info("OK -> plantilla '{$name}' actualizada (es, en).");
        }
    }

    // ----------------------------------------------------------------------
    // Bloque de "tarjeta" reutilizable con los datos del ticket.
    // Estilos en linea porque los clientes de correo no leen <style>/clases.
    // ----------------------------------------------------------------------

    private function infoCard(array $rows, ?string $buttonLabel = null): string
    {
        $tr = '';
        $labels = array_keys($rows);
        $last = count($labels) - 1;
        foreach ($labels as $i => $label) {
            $value  = $rows[$label];
            $border = $i < $last ? 'border-bottom:1px solid #eef0f3;' : '';
            $tr .= '<tr>'
                . '<td style="padding:11px 18px;font-size:11px;font-weight:600;letter-spacing:.6px;'
                . 'text-transform:uppercase;color:#8a909c;white-space:nowrap;vertical-align:middle;'
                . 'width:38%;font-family:Arial,Helvetica,sans-serif;' . $border . '">' . $label . '</td>'
                . '<td style="padding:11px 18px;font-size:15px;color:#111827;font-weight:600;'
                . 'vertical-align:middle;font-family:Arial,Helvetica,sans-serif;' . $border . '">' . $value . '</td>'
                . '</tr>';
        }

        $card = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
              . 'style="background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;margin:18px 0;'
              . 'border-collapse:separate;overflow:hidden;">'
              . '<tbody>' . $tr . '</tbody></table>';

        if ($buttonLabel) {
            $card .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">'
                  . '<tr><td align="center">'
                  . '<a href="{ticket_url}" target="_blank" rel="noopener" '
                  . 'style="background:#6676ef;color:#ffffff;display:inline-block;padding:12px 28px;'
                  . 'border-radius:8px;font-size:15px;font-weight:600;text-decoration:none;font-family:Arial,Helvetica,sans-serif;">'
                  . $buttonLabel . '</a>'
                  . '</td></tr></table>';
        }

        return $card;
    }

    // ----------------------------------------------------------------------
    // 1) TICKET CREADO
    // ----------------------------------------------------------------------
    private function newTicketTemplate(): array
    {
        $variables = json_encode([
            'Company Name'      => 'company_name',
            'App Url'           => 'app_url',
            'Ticket Id'         => 'ticket_id',
            'Subject'           => 'subject',
            'Status'            => 'status',
            'Priority'          => 'priority',
            'Created User Name' => 'created_user_name',
            'Email'             => 'email',
            'Ticket Url'        => 'ticket_url',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $es = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">Se creó un nuevo ticket de soporte</p>'
            . '<p style="font-size:14px;color:#6b7280;margin:0 0 8px;">'
            . 'Recibes este correo porque eres parte del equipo que da seguimiento a los tickets.</p>'
            . $this->infoCard([
                'Ticket #'         => '{ticket_id}',
                'Asunto'           => '{subject}',
                'Estado'           => '{status}',
                'Prioridad'        => '{priority}',
                'Creado por'       => '{created_user_name}',
                'Correo de origen' => '{email}',
            ], 'Ver el ticket')
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        $en = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">A new support ticket was created</p>'
            . '<p style="font-size:14px;color:#6b7280;margin:0 0 8px;">'
            . 'You are receiving this email because you are part of the team that follows up on tickets.</p>'
            . $this->infoCard([
                'Ticket #'       => '{ticket_id}',
                'Subject'        => '{subject}',
                'Status'         => '{status}',
                'Priority'       => '{priority}',
                'Created by'     => '{created_user_name}',
                'Source email'   => '{email}',
            ], 'View the ticket')
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        return [
            'variables' => $variables,
            'subject'   => [
                'es' => 'Nuevo ticket #{ticket_id}: {subject}',
                'en' => 'New ticket #{ticket_id}: {subject}',
            ],
            'lang' => ['es' => $es, 'en' => $en],
        ];
    }

    // ----------------------------------------------------------------------
    // 2) TICKET ASIGNADO
    // ----------------------------------------------------------------------
    private function assignedTemplate(): array
    {
        $variables = json_encode([
            'Company Name'       => 'company_name',
            'App Url'            => 'app_url',
            'Assigned User Name' => 'assigned_user_name',
            'Created User Name'  => 'created_user_name',
            'Ticket Id'          => 'ticket_id',
            'Subject'            => 'subject',
            'Status'             => 'status',
            'Priority'           => 'priority',
            'Email'              => 'email',
            'Ticket Url'         => 'ticket_url',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $es = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">Hola {assigned_user_name},</p>'
            . '<p style="font-size:15px;color:#374151;margin:0 0 8px;">'
            . 'Se te asignó un ticket de soporte. Estos son los detalles:</p>'
            . $this->infoCard([
                'Ticket #'   => '{ticket_id}',
                'Asunto'     => '{subject}',
                'Estado'     => '{status}',
                'Prioridad'  => '{priority}',
                'Creado por' => '{created_user_name}',
            ], 'Atender el ticket')
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        $en = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">Hi {assigned_user_name},</p>'
            . '<p style="font-size:15px;color:#374151;margin:0 0 8px;">'
            . 'A support ticket has been assigned to you. Here are the details:</p>'
            . $this->infoCard([
                'Ticket #'   => '{ticket_id}',
                'Subject'    => '{subject}',
                'Status'     => '{status}',
                'Priority'   => '{priority}',
                'Created by' => '{created_user_name}',
            ], 'Work on the ticket')
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        return [
            'variables' => $variables,
            'subject'   => [
                'es' => 'Se te asignó el ticket #{ticket_id}: {subject}',
                'en' => 'Ticket #{ticket_id} assigned to you: {subject}',
            ],
            'lang' => ['es' => $es, 'en' => $en],
        ];
    }

    // ----------------------------------------------------------------------
    // 3) RESPUESTA / CIERRE (va a quien creó el ticket / correo de origen)
    // ----------------------------------------------------------------------
    private function replyTemplate(): array
    {
        $variables = json_encode([
            'Company Name'      => 'company_name',
            'App Url'           => 'app_url',
            'Ticket Name'       => 'ticket_name',
            'Ticket Id'         => 'ticket_id',
            'Subject'           => 'subject',
            'Status'            => 'status',
            'Priority'          => 'priority',
            'Reply Description' => 'reply_description',
            'Email'             => 'email',
            'Ticket Url'        => 'ticket_url',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $replyBoxEs = '<p style="font-size:14px;color:#6b7280;margin:16px 0 4px;">Mensaje:</p>'
            . '<div style="background:#ffffff;border-left:4px solid #6676ef;padding:12px 16px;'
            . 'font-size:14px;color:#111827;border-radius:4px;">{reply_description}</div>';

        $replyBoxEn = '<p style="font-size:14px;color:#6b7280;margin:16px 0 4px;">Message:</p>'
            . '<div style="background:#ffffff;border-left:4px solid #6676ef;padding:12px 16px;'
            . 'font-size:14px;color:#111827;border-radius:4px;">{reply_description}</div>';

        $es = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">Hola {ticket_name},</p>'
            . '<p style="font-size:15px;color:#374151;margin:0 0 8px;">'
            . 'Hay una actualización en tu ticket de soporte.</p>'
            . $this->infoCard([
                'Ticket #'  => '{ticket_id}',
                'Asunto'    => '{subject}',
                'Estado'    => '{status}',
                'Prioridad' => '{priority}',
            ])
            . $replyBoxEs
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">'
            . '<tr><td align="center">'
            . '<a href="{ticket_url}" target="_blank" rel="noopener" '
            . 'style="background:#6676ef;color:#ffffff;display:inline-block;padding:12px 28px;'
            . 'border-radius:8px;font-size:15px;font-weight:600;text-decoration:none;font-family:Arial,Helvetica,sans-serif;">'
            . 'Ver el ticket</a></td></tr></table>'
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        $en = '<p style="font-size:16px;color:#111827;margin:0 0 4px;">Hi {ticket_name},</p>'
            . '<p style="font-size:15px;color:#374151;margin:0 0 8px;">'
            . 'There is an update on your support ticket.</p>'
            . $this->infoCard([
                'Ticket #'  => '{ticket_id}',
                'Subject'   => '{subject}',
                'Status'    => '{status}',
                'Priority'  => '{priority}',
            ])
            . $replyBoxEn
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;">'
            . '<tr><td align="center">'
            . '<a href="{ticket_url}" target="_blank" rel="noopener" '
            . 'style="background:#6676ef;color:#ffffff;display:inline-block;padding:12px 28px;'
            . 'border-radius:8px;font-size:15px;font-weight:600;text-decoration:none;font-family:Arial,Helvetica,sans-serif;">'
            . 'View the ticket</a></td></tr></table>'
            . '<p style="font-size:13px;color:#9ca3af;margin:16px 0 0;">— {company_name}</p>';

        return [
            'variables' => $variables,
            'subject'   => [
                'es' => 'Actualización del ticket #{ticket_id}: {subject}',
                'en' => 'Update on ticket #{ticket_id}: {subject}',
            ],
            'lang' => ['es' => $es, 'en' => $en],
        ];
    }
}
