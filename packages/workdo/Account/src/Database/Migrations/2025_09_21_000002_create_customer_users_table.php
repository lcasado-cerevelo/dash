<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
         if (!Schema::hasTable('customer_users')) {
           Schema::create('customer_users', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamps();
    
                $t->unique(['customer_id','user_id']);
                $t->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
            DB::table('customer_users')->insertUsing(
                ['customer_id','user_id','created_at','updated_at'],
                DB::table('customers')
                  ->whereNotNull('user_id')
                  ->select('id','user_id', DB::raw('NOW()'), DB::raw('NOW()'))
            );        
         }
    }
    public function down(): void
    {
        Schema::dropIfExists('customer_users');
    }
};
