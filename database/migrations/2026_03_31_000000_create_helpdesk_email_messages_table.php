<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHelpdeskEmailMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('helpdesk_email_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('conversion_id')->nullable();
            $table->unsignedBigInteger('mailbox_id')->nullable();

            $table->string('message_id')->unique();

            $table->string('in_reply_to')->nullable();
            $table->text('references_text')->nullable();

            $table->string('subject')->nullable();
            $table->string('from_email')->nullable();

            $table->enum('direction', ['inbound', 'outbound']);

            $table->timestamps();

            // índices
            $table->index('ticket_id');
            $table->index('in_reply_to');

            // foreign keys (opcionales, si quieres integridad)
            $table->foreign('ticket_id')->references('id')->on('helpdesk_tickets')->onDelete('cascade');
            $table->foreign('conversion_id')->references('id')->on('helpdesk_conversions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('helpdesk_email_messages');
    }
}