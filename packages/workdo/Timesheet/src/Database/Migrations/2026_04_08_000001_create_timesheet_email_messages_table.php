<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timesheet_email_messages')) {
            Schema::create('timesheet_email_messages', function (Blueprint $table) {
                $table->id();

                // The primary timesheet created from this email (first one when multi-day split)
                $table->unsignedBigInteger('timesheet_id')->nullable();

                // Raw message identifier for idempotency
                $table->string('message_id')->unique();

                // Email metadata
                $table->string('from_email')->nullable();
                $table->string('subject')->nullable();

                // The resolved employee user
                $table->unsignedBigInteger('employee_user_id')->nullable();

                // How many timesheets were created from this email (multi-day splits)
                $table->unsignedTinyInteger('timesheets_created')->default(0);

                // Store the raw header values for traceability
                $table->string('x_employee_email')->nullable();
                $table->string('x_employee_project')->nullable();
                $table->string('x_employee_task')->nullable();
                $table->string('x_employee_customer')->nullable();
                $table->string('x_employee_client')->nullable();

                $table->timestamps();

                $table->index('employee_user_id');
                $table->index('timesheet_id');

                $table->foreign('timesheet_id')
                      ->references('id')
                      ->on('timesheets')
                      ->onDelete('set null');

                $table->foreign('employee_user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_email_messages');
    }
};
