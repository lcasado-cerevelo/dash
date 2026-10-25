<?php
// database/migrations/2025_12_12_000004_create_ticket_inbound_processed.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_inbound_processed', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mailbox_id');
            $t->string('message_id')->index();
            $t->string('imap_uid')->nullable()->index();
            $t->timestamp('processed_at');

            $t->unique(['mailbox_id','message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_inbound_processed');
    }
};
