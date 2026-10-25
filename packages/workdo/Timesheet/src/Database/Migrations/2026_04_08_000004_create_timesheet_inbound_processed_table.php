<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla análoga a ticket_inbound_processed.
     * Guarda el Message-ID de cada correo ya procesado para garantizar
     * idempotencia: si el cron corre dos veces o el servidor reinicia a mitad,
     * nunca se crea el mismo timesheet dos veces.
     */
    public function up(): void
    {
        Schema::create('timesheet_inbound_processed', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mailbox_id');
            $t->string('message_id')->index();           // Message-ID del email (hash natural de idempotencia)
            $t->string('imap_uid')->nullable()->index(); // UID IMAP, útil para debug
            $t->string('from_email')->nullable();
            $t->string('status')->default('ok');         // ok | skipped_no_map | skipped_parse_error | error
            $t->unsignedSmallInteger('timesheets_created')->default(0);
            $t->timestamp('processed_at');

            // La combinación mailbox + message_id es el índice de unicidad real
            $t->unique(['mailbox_id', 'message_id'], 'ts_inbound_processed_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_inbound_processed');
    }
};
