<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_clients_assigned', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_client_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('customer_client_id')
                ->references('id')
                ->on('customer_clients')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(['customer_client_id', 'user_id'], 'uq_customer_clients_assigned');
            $table->index(['user_id', 'customer_client_id'], 'idx_customer_clients_assigned_user_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_clients_assigned');
    }
};