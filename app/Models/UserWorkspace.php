<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabla pivot que relaciona usuarios con workspaces.
 *
 * Permite que un usuario pertenezca a múltiples workspaces.
 * El campo is_default indica el workspace "home" del usuario.
 */
class UserWorkspace extends Pivot
{
    protected $table = 'user_workspaces';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'role',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workspace()
    {
        return $this->belongsTo(WorkSpace::class);
    }
}
