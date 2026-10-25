<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->after('id');
            $table->unsignedBigInteger('customer_id')->nullable()->after('task_id');
            $table->unsignedBigInteger('customer_client_id')->nullable()->after('customer_id');

            $table->foreign('task_id')->references('id')->on('tasks')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('customer_client_id')->references('id')->on('customer_clients')->nullOnDelete();

            $table->index(['task_id','customer_id','customer_client_id'], 'tickets_task_customer_client_idx');
            $table->string('status', 100)->default('new')->change();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['task_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['customer_client_id']);
            $table->dropIndex('tickets_task_customer_client_idx');

            $table->dropColumn(['task_id','customer_id','customer_client_id']);
        });
    }
};
