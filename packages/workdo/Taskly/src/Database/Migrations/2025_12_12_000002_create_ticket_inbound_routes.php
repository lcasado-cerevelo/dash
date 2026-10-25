<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_inbound_routes', function (Blueprint $t) {
            $t->id();

            $t->foreignId('mailbox_id')
                ->constrained('ticket_inbound_mailboxes')
                ->cascadeOnDelete();

            $t->unsignedBigInteger('project_id')->nullable();

            $t->string('to_email')->nullable();
            $t->string('to_contains')->nullable();
            $t->string('from_contains')->nullable();
            $t->string('subject_contains')->nullable();

            $t->boolean('allow_new_ticket')->default(true);
            $t->integer('sort_order')->default(100);
            $t->boolean('is_active')->default(true);

            $t->timestamps();

            $t->index(['mailbox_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_inbound_routes');
    }
};