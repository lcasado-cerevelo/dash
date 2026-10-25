<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('status');
        });

        // Si quieres marcar algunas existentes manualmente luego, déjalo así.
        // Si quieres mover ciertas tareas ya existentes a service, haz un UPDATE luego.
        DB::table('tasks')->whereNull('is_service')->update(['is_service' => 0]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};