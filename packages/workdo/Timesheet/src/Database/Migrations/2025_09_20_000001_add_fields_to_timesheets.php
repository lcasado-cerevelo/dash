<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Agregar columnas si no existen
        Schema::table('timesheets', function (Blueprint $table) {
            if (!Schema::hasColumn('timesheets', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('timesheets', 'task_id')) {
                $table->unsignedBigInteger('task_id')->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('timesheets', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('task_id');
            }
            if (!Schema::hasColumn('timesheets', 'customer_client_id')) {
                $table->unsignedBigInteger('customer_client_id')->nullable()->after('customer_id');
            }
        });

        Schema::table('timesheets', function (Blueprint $table) {
            // Nota: los nombres por defecto quedarán como timesheets_<col>_foreign
            if (Schema::hasTable('projects') && Schema::hasColumn('timesheets', 'project_id')) {
                // Evita error si ya existe la FK
                try { $table->foreign('project_id', 'fk_timesheets_project')->references('id')->on('projects')->restrictOnDelete(); } catch (\Throwable $e) {}
            }
            if (Schema::hasTable('tasks') && Schema::hasColumn('timesheets', 'task_id')) {
                try { $table->foreign('task_id', 'fk_timesheets_task')->references('id')->on('tasks')->restrictOnDelete(); } catch (\Throwable $e) {}
            }
            if (Schema::hasTable('customers') && Schema::hasColumn('timesheets', 'customer_id')) {
                try { $table->foreign('customer_id', 'fk_timesheets_customer')->references('id')->on('customers')->restrictOnDelete(); } catch (\Throwable $e) {}
            }
            if (Schema::hasTable('customer_clients') && Schema::hasColumn('timesheets', 'customer_client_id')) {
                try { $table->foreign('customer_client_id', 'fk_timesheets_customer_client')->references('id')->on('customer_clients')->restrictOnDelete(); } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        // Eliminar FKs y columnas si existen
        Schema::table('timesheets', function (Blueprint $table) {
            // Dropear FKs primero
            if (Schema::hasColumn('timesheets', 'project_id')) {
                // nombre por defecto: timesheets_project_id_foreign
                try { $table->dropForeign(['project_id']); } catch (\Throwable $e) {}
                $table->dropColumn('project_id');
            }
            if (Schema::hasColumn('timesheets', 'task_id')) {
                try { $table->dropForeign(['task_id']); } catch (\Throwable $e) {}
                $table->dropColumn('task_id');
            }
            if (Schema::hasColumn('timesheets', 'customer_id')) {
                try { $table->dropForeign(['customer_id']); } catch (\Throwable $e) {}
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('timesheets', 'customer_client_id')) {
                try { $table->dropForeign(['customer_client_id']); } catch (\Throwable $e) {}
                $table->dropColumn('customer_client_id');
            }
        });
    }
};
