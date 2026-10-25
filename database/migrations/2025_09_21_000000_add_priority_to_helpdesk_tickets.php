<?php

// database/migrations/2025_09_21_000000_add_priority_to_helpdesk_tickets.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(3)->after('status');
        });
    }
    public function down(): void {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
