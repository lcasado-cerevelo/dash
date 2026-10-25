<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_ticket_id')->nullable()->after('customer_client_id');

            $table->foreign('parent_ticket_id')
                ->references('id')
                ->on('helpdesk_tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['parent_ticket_id']);
            $table->dropColumn('parent_ticket_id');
        });
    }
};