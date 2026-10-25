<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Ajusta 'after' según tu estructura (por ejemplo, después de 'task_id' o 'type')
            $table->unsignedBigInteger('ticket_id')->nullable()->after('task_id');

            // Índice y FK (si tu tabla de tickets es 'helpdesk_tickets')
            $table->foreign('ticket_id')
                  ->references('id')->on('helpdesk_tickets')
                  ->nullOnDelete(); // si borran el ticket, deja el timesheet con NULL

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropIndex(['ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
