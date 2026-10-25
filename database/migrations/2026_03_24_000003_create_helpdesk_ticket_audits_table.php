<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('helpdesk_ticket_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('changed_by');
            $table->string('field_name', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('helpdesk_tickets')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['ticket_id', 'field_name'], 'idx_helpdesk_ticket_audits_ticket_field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_ticket_audits');
    }
};