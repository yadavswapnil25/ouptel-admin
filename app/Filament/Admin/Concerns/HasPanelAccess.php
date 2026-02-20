<?php

namespace App\Filament\Admin\Concerns;

trait HasPanelAccess
{
    /**
     * Each resource should override this with its permission key.
     * e.g. protected static string $permissionKey = 'manage-users';
     */
     // protected static string $permissionKey = '';

    /**
     * Determine if the resource is accessible to the current user.
     * Super admins (admin == 1) always have access.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Users with admin=1 are super admins — full access
        if ($user->admin == '1') {
            return true;
        }

        // If no permission key is defined, fall back to parent behaviour
        if (empty(static::$permissionKey)) {
            return parent::canAccess();
        }

        return $user->hasAdminPermission(static::$permissionKey);
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }
}
