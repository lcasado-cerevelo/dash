<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToTimesheets extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            if (!Schema::hasColumn('timesheets', 'from_time')) {
                $table->text('from_time')->default(false)->after('date');
            }
            if (!Schema::hasColumn('timesheets', 'to_time')) {
                $table->text('to_time')->default(false)->after('from_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            if (Schema::hasColumn('timesheets', 'from_time')) {
                $table->dropColumn('from_time');
            }
            if (Schema::hasColumn('timesheets', 'to_time')) {
                $table->dropColumn('to_time');
            }
        });
    }
}
