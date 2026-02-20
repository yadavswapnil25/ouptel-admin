<?php

namespace App\Filament\Admin\Concerns;

trait HasPageAccess
{
    /**
     * Optional permission key for a page.
     * If null, the page is super-admin only.
     */
    protected static ?string $permissionKey = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Legacy super admin bypass.
        if ($user->admin == '1') {
            return true;
        }

        // Non-super-admin users must have an explicit permission key.
        if (empty(static::$permissionKey)) {
            return false;
        }

        return $user->hasAdminPermission(static::$permissionKey);
    }
}


