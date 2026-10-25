<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_inbound_project_maps', function (Blueprint $t) {
            $t->id();

            $t->foreignId('mailbox_id')
                ->nullable()
                ->constrained('ticket_inbound_mailboxes')
                ->nullOnDelete();

            $t->string('match_type');   // email | domain
            $t->string('match_value');  // alguien@cliente.com | @cliente.com
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('customer_client_id')->nullable();
            $t->unsignedBigInteger('task_id')->nullable();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('priority')->nullable();   // low|medium|high
            $t->string('assign_to')->nullable();  // "1,2" para Taskly
            $t->integer('sort_order')->default(100);
            $t->boolean('is_active')->default(true);

            $t->timestamps();

            $t->index(['mailbox_id', 'match_type', 'match_value', 'is_active'], 'ti_project_maps_lookup_idx');
            $t->index(['project_id', 'is_active'], 'ti_project_maps_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_inbound_project_maps');
    }
};