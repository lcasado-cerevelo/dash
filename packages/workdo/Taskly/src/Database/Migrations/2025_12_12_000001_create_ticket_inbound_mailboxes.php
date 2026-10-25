<?php
// database/migrations/2025_12_12_000001_create_ticket_inbound_mailboxes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_inbound_mailboxes', function (Blueprint $t) {
            $t->id();
            $t->string('name');                   // "Soporte General"
            $t->string('imap_host');              // imap.tudominio.com
            $t->unsignedInteger('imap_port')->default(993);
            $t->boolean('imap_ssl')->default(true);
            $t->string('username');               // email login
            $t->text('password_enc');             // encriptado con Crypt
            $t->string('folder')->default('INBOX');
            $t->boolean('is_active')->default(true);

            // Opcional: mover procesados a carpeta
            $t->string('processed_folder')->nullable(); // ej: "Processed"
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_inbound_mailboxes');
    }
};
