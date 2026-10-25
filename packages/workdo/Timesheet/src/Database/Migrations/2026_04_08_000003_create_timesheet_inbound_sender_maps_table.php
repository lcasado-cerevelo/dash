<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla análoga a ticket_inbound_project_maps pero para timesheets.
     *
     * Permite mapear el email/dominio del REMITENTE (From) a:
     *  - El usuario empleado (employee_user_id)
     *  - Proyecto, tarea, customer, customer_client por defecto
     *  - Workspace
     *
     * La resolución se hace en orden de sort_order:
     *   1. match_type = 'email'  → match exacto del From
     *   2. match_type = 'domain' → match del dominio @empresa.com
     */
    public function up(): void
    {
        Schema::create('timesheet_inbound_sender_maps', function (Blueprint $t) {
            $t->id();

            $t->foreignId('mailbox_id')
                ->nullable()
                ->constrained('timesheet_inbound_mailboxes')
                ->nullOnDelete();

            // 'email' → match exacto:  john@acme.com
            // 'domain' → match dominio: @acme.com
            $t->string('match_type');
            $t->string('match_value');   // el email o dominio a comparar (lowercase)

            // El empleado al que pertenecen los timesheets de este remitente.
            // NULL = se usa el X-Employee-Email header como fallback.
            $t->unsignedBigInteger('employee_user_id')->nullable();

            // Contexto por defecto para los timesheets creados
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('task_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('customer_client_id')->nullable();
            $t->unsignedBigInteger('workspace_id')->nullable();

            $t->integer('sort_order')->default(100);
            $t->boolean('is_active')->default(true);

            $t->timestamps();

            $t->index(
                ['mailbox_id', 'match_type', 'match_value', 'is_active'],
                'ts_sender_maps_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_inbound_sender_maps');
    }
};
