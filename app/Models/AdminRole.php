<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    protected $fillable = ['name', 'description', 'is_super_admin'];

    protected $casts = [
        'is_super_admin' => 'boolean',
    ];

    /**
     * The permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_role_permissions',
            'role_id',
            'permission_id'
        );
    }

    /**
     * The admin users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'admin_user_roles',
            'role_id',
            'user_id',
            'id',      // local key on admin_roles
            'user_id'  // foreign key on Wo_Users
        );
    }

    /**
     * Check if this role grants a specific permission key.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->permissions->contains('key', $key);
    }
}
