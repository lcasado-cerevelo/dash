<?php

// database/migrations/2025_09_21_000001_create_customer_clients_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
         if (!Schema::hasTable('customer_clients_users')) {
             Schema::create('customer_clients_users', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_client_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamps();
    
                $t->unique(['customer_client_id','user_id'], 'ccu_unique');
    
                $t->foreign('customer_client_id')
                  ->references('id')->on('customer_clients')
                  ->onDelete('cascade');
    
                $t->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
            });
            DB::statement("
              INSERT IGNORE INTO customer_clients_users (customer_client_id,user_id,created_at,updated_at)
              SELECT cc.id, u.id, NOW(), NOW()
              FROM customer_clients cc
              JOIN users u ON u.email = cc.email
              WHERE cc.email IS NOT NULL AND cc.email <> ''
            ");
         }

        // Backfill opcional: empareja por email (customer_clients.email == users.email)
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_clients_users');
    }
};
