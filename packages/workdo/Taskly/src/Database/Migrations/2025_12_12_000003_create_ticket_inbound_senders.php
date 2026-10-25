<?php
// database/migrations/2025_12_12_000003_create_ticket_inbound_senders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_inbound_senders', function (Blueprint $t) {
            $t->id();
            $t->string('email');                    // match exacto: alguien@cliente.com
            $t->unsignedBigInteger('customer_id');  // customer fijo
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_inbound_senders');
    }
};
