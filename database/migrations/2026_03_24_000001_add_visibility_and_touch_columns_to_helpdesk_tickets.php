<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('helpdesk_tickets', 'visible_to_user_ids')) {
                $table->json('visible_to_user_ids')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('helpdesk_tickets', 'touched_by_user_ids')) {
                $table->json('touched_by_user_ids')->nullable()->after('visible_to_user_ids');
            }
        });

        // user_id hoy te aparece como string en la migración original.
        // Como ya lo estás usando como FK lógica a users.id, te conviene normalizarlo a bigint nullable.
        DB::statement("ALTER TABLE helpdesk_tickets MODIFY user_id BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'touched_by_user_ids')) {
                $table->dropColumn('touched_by_user_ids');
            }
            if (Schema::hasColumn('helpdesk_tickets', 'visible_to_user_ids')) {
                $table->dropColumn('visible_to_user_ids');
            }
        });

        DB::statement("ALTER TABLE helpdesk_tickets MODIFY user_id VARCHAR(255) NULL");
    }
};