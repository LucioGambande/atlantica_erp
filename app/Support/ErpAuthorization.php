<?php

namespace App\Support;

use App\Models\User;

class ErpAuthorization
{
    public static function userCan(string $permission, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can($permission) ?? false;
    }

    public static function isAdmin(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->hasRole('admin') ?? false;
    }
}
