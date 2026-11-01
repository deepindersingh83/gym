<?php

namespace App\Support;

use App\Enums\AdminRole;
use App\Models\User;

/** What each platform admin role may do in /admin. */
class AdminAccess
{
    protected const MATRIX = [
        'gyms.view' => ['owner', 'support', 'sales', 'finance'],
        'gyms.create' => ['owner', 'sales'],
        'gyms.edit' => ['owner', 'support', 'sales'],
        'gyms.delete' => ['owner'],
        'gyms.open' => ['owner', 'support'],
        'gyms.trial' => ['owner', 'support', 'sales'],
        'gyms.owner_password' => ['owner', 'support'],
        'gyms.payment' => ['owner', 'finance'],
        'gyms.credits' => ['owner', 'support', 'finance'],
        'gyms.suspend' => ['owner', 'support'],
        'gyms.export' => ['owner', 'support'],
        'usage' => ['owner', 'support', 'sales', 'finance'],
        'plans' => ['owner'],
        'partners' => ['owner', 'sales'],
        'commissions' => ['owner', 'finance'],
        'payments' => ['owner', 'finance'],
        'revenue' => ['owner', 'finance'],
        'coupons' => ['owner', 'sales'],
        'settings' => ['owner'],
        'team' => ['owner'],
        'admin_log' => ['owner'],
    ];

    public static function role(?User $user = null): ?AdminRole
    {
        $user ??= auth()->user();

        return $user instanceof User ? $user->adminRole() : null;
    }

    public static function can(string $ability, ?User $user = null): bool
    {
        $role = static::role($user);

        return $role !== null && in_array($role->value, self::MATRIX[$ability] ?? [], true);
    }
}
