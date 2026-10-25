<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('contract_number', 20)->nullable();
            $table->string('fiscal_month', 20)->nullable();
            $table->string('fiscal_period', 20)->nullable();
            $table->string('attention_to', 250)->nullable();
            $table->longText('invoice_note')->nullable();
            $table->string('description_note', 250)->nullable();
        });
    }
    
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'contract_number','fiscal_month','fiscal_period',
                'attention_to','invoice_note','description_note'
            ]);
        });
    }
};