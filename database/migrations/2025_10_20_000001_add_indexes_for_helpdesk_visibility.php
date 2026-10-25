<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =========================
        // helpdesk_tickets
        // =========================
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            // Índices para filtros/joins de visibilidad y UI
            $table->index('workspace', 'hdt_ws_idx');
            $table->index('user_id', 'hdt_user_idx');
            $table->index('task_id', 'hdt_task_idx');
            $table->index('customer_id', 'hdt_customer_idx');
            $table->index('customer_client_id', 'hdt_customer_client_idx');
            $table->index('created_by', 'hdt_created_by_idx');
            $table->index('created_at', 'hdt_created_at_idx');
            $table->index('status', 'hdt_status_idx');      // útil para tabs/contadores
            $table->index('category', 'hdt_category_idx');  // útil para filtro por categoría
        });

        // =========================
        // tasks
        // =========================
        Schema::table('tasks', function (Blueprint $table) {
            // Para joins por proyecto
            if (! $this->hasIndex('tasks', 'tasks_project_id_idx')) {
                $table->index('project_id', 'tasks_project_id_idx');
            }
            // NOTA: tasks.assign_to (CSV) NO se puede indexar de forma útil.
            // Si en el futuro migras a pivote task_users o JSON+columna generada,
            // ahí sí valdrá la pena indexar.
        });

        // =========================
        // user_projects (acceso a proyectos por usuario)
        // =========================
        Schema::table('user_projects', function (Blueprint $table) {
            // Composite index para lookups por (project_id, user_id)
            if (! $this->hasIndex('user_projects', 'user_projects_project_user_idx')) {
                $table->index(['project_id', 'user_id'], 'user_projects_project_user_idx');
            }
            if (! $this->hasIndex('user_projects', 'user_projects_user_id_idx')) {
                $table->index('user_id', 'user_projects_user_id_idx');
            }
        });

        // =========================
        // customer_clients (relación customer -> customer_clients)
        // =========================
        Schema::table('customer_clients', function (Blueprint $table) {
            if (! $this->hasIndex('customer_clients', 'customer_clients_customer_id_idx')) {
                $table->index('customer_id', 'customer_clients_customer_id_idx');
            }
        });

        // =========================
        // customer_clients_users (pivote user <-> customer_client)
        // =========================
        Schema::table('customer_clients_users', function (Blueprint $table) {
            if (! $this->hasIndex('customer_clients_users', 'ccu_customer_client_user_idx')) {
                $table->index(['customer_client_id', 'user_id'], 'ccu_customer_client_user_idx');
            }
            if (! $this->hasIndex('customer_clients_users', 'ccu_user_id_idx')) {
                $table->index('user_id', 'ccu_user_id_idx');
            }
        });

        // =========================
        // customers (relación client -> customers)
        // =========================
        Schema::table('customers', function (Blueprint $table) {
            if (! $this->hasIndex('customers', 'customers_user_id_idx')) {
                $table->index('user_id', 'customers_user_id_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // helpdesk_tickets
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_ws_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_user_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_task_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_customer_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_customer_client_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_created_by_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_created_at_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_status_idx');
            $this->dropIndexIfExists('helpdesk_tickets', 'hdt_category_idx');
        });

        // tasks
        Schema::table('tasks', function (Blueprint $table) {
            $this->dropIndexIfExists('tasks', 'tasks_project_id_idx');
        });

        // user_projects
        Schema::table('user_projects', function (Blueprint $table) {
            $this->dropIndexIfExists('user_projects', 'user_projects_project_user_idx');
            $this->dropIndexIfExists('user_projects', 'user_projects_user_id_idx');
        });

        // customer_clients
        Schema::table('customer_clients', function (Blueprint $table) {
            $this->dropIndexIfExists('customer_clients', 'customer_clients_customer_id_idx');
        });

        // customer_clients_users
        Schema::table('customer_clients_users', function (Blueprint $table) {
            $this->dropIndexIfExists('customer_clients_users', 'ccu_customer_client_user_idx');
            $this->dropIndexIfExists('customer_clients_users', 'ccu_user_id_idx');
        });

        // customers
        Schema::table('customers', function (Blueprint $table) {
            $this->dropIndexIfExists('customers', 'customers_user_id_idx');
        });
    }

    /**
     * Helpers (simples) para evitar fallos si corres dos veces.
     * Nota: Laravel no trae "hasIndex" nativo; usamos información del esquema.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        // Compatible con MySQL/MariaDB
        try {
            $connection = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $connection->listTableIndexes($table);
            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            // Si falla Doctrine (p.ej. en SQLite), asumimos que NO existe
            return false;
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            if ($this->hasIndex($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            }
        } catch (\Throwable $e) {
            // Ignorar errores al hacer rollback de índices inexistentes
        }
    }
};
