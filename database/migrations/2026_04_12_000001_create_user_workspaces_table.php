<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear la tabla pivot sin foreign keys (más compatible con cPanel/MySQL compartido)
        if (!Schema::hasTable('user_workspaces')) {
            Schema::create('user_workspaces', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('workspace_id');
                $table->string('role', 100)->nullable();
                $table->tinyInteger('is_default')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique(['user_id', 'workspace_id']);
                $table->index('user_id');
                $table->index('workspace_id');
            });
        }

        // 2. Migrar datos: workspace_id de cada usuario
        $now = date('Y-m-d H:i:s');

        $users = DB::table('users')
            ->whereNotNull('workspace_id')
            ->where('workspace_id', '!=', 0)
            ->select('id', 'workspace_id', 'active_workspace')
            ->get();

        foreach ($users as $user) {
            $wsExists = DB::table('work_spaces')
                ->where('id', $user->workspace_id)
                ->exists();

            if ($wsExists) {
                DB::table('user_workspaces')
                    ->insertOrIgnore([
                        'user_id'      => $user->id,
                        'workspace_id' => $user->workspace_id,
                        'is_default'   => 1,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
            }

            // Si active_workspace es diferente, agregarlo también
            if (!empty($user->active_workspace) && $user->active_workspace != $user->workspace_id) {
                $activeExists = DB::table('work_spaces')
                    ->where('id', $user->active_workspace)
                    ->exists();

                if ($activeExists) {
                    DB::table('user_workspaces')
                        ->insertOrIgnore([
                            'user_id'      => $user->id,
                            'workspace_id' => $user->active_workspace,
                            'is_default'   => 0,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);
                }
            }
        }

        // 3. Registrar workspaces creados por company owners
        $ownedWorkspaces = DB::table('work_spaces')
            ->where('created_by', '!=', 0)
            ->select('id', 'created_by')
            ->get();

        foreach ($ownedWorkspaces as $ws) {
            DB::table('user_workspaces')
                ->insertOrIgnore([
                    'user_id'      => $ws->created_by,
                    'workspace_id' => $ws->id,
                    'is_default'   => 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workspaces');
    }
};