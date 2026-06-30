<?php

namespace App\Support;

use App\Models\User;

class InvoicePrintAuthorization
{
    public static function canPrint(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can('print invoices') || $user->can('manage invoices');
    }

    public static function canManage(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can('manage invoices') ?? false;
    }
}
