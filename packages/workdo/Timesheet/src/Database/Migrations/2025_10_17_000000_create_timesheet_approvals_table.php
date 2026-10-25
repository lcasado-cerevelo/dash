<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('timesheet_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('user_id'); // quien realiza la acción
            $table->enum('action', ['approved','unapproved']);
            $table->timestamps();

            $table->index(['timesheet_id','created_at']);
            $table->index(['user_id','created_at']);
            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_approvals');
    }
};
