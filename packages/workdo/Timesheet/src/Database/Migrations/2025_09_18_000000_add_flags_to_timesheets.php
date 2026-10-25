<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFlagsToTimesheets extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            if (!Schema::hasColumn('timesheets', 'description')) {
                $table->longtext('description')->default(false)->after('date');
            }
            if (!Schema::hasColumn('timesheets', 'validated')) {
                $table->boolean('validated')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('timesheets', 'invoiced')) {
                $table->boolean('invoiced')->default(false)->after('validated');
            }
            if (!Schema::hasColumn('timesheets', 'paid')) {
                $table->boolean('paid')->default(false)->after('invoiced');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            if (Schema::hasColumn('timesheets', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('timesheets', 'validated')) {
                $table->dropColumn('validated');
            }
            if (Schema::hasColumn('timesheets', 'invoiced')) {
                $table->dropColumn('invoiced');
            }
            if (Schema::hasColumn('timesheets', 'paid')) {
                $table->dropColumn('paid');
            }
        });
    }
}
