<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_inbound_mailboxes', function (Blueprint $t) {
            $t->id();
            $t->string('name');                          // Nombre descriptivo, ej: "Timesheets RRHH"
            $t->string('imap_host');                     // imap.tudominio.com
            $t->unsignedInteger('imap_port')->default(993);
            $t->boolean('imap_ssl')->default(true);
            $t->string('username');                      // correo login IMAP
            $t->text('password_enc');                    // encriptado con Crypt::encryptString()
            $t->string('folder')->default('INBOX');
            $t->string('processed_folder')->nullable();  // carpeta destino tras procesar, ej: "Processed"
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_inbound_mailboxes');
    }
};
