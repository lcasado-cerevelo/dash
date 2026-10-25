<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_inbound_routes', function (Blueprint $t) {
            if (!Schema::hasColumn('ticket_inbound_routes', 'project_id')) {
                $t->unsignedBigInteger('project_id')->nullable()->after('mailbox_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_inbound_routes', function (Blueprint $t) {
            if (Schema::hasColumn('ticket_inbound_routes', 'project_id')) {
                $t->dropColumn('project_id');
            }
        });
    }
};
