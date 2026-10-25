<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_goverment')->default(false);
            $table->string('invoice_abrv', 4)->nullable();
            $table->longText('certification_text')->nullable();
            $table->string('invoice_att', 250)->nullable();
        });
    }
    
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_goverment','invoice_abrv','certification_text','invoice_att']);
        });
    }
};