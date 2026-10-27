<?php

namespace App\Support;

use App\Enums\StaffRole;

/**
 * Role permissions inside a gym. Abilities are "<area>.<action>" where action
 * is view, create, edit or delete.
 */
class Access
{
    protected const O = StaffRole::Owner;

    protected const M = StaffRole::Manager;

    protected const T = StaffRole::Trainer;

    protected const R = StaffRole::Receptionist;

    /** @return array<string, array<string, list<StaffRole>>> */
    protected static function matrix(): array
    {
        $o = [self::O];
        $om = [self::O, self::M];
        $omr = [self::O, self::M, self::R];
        $omt = [self::O, self::M, self::T];
        $all = [self::O, self::M, self::R, self::T];

        return [
            'members' => ['view' => $all, 'create' => $omr, 'edit' => $omr, 'delete' => $om],
            'subscriptions' => ['view' => $omr, 'create' => $omr, 'edit' => $om, 'delete' => $o],
            'payments' => ['view' => $omr, 'create' => $omr, 'edit' => $om, 'delete' => $o],
            'plans' => ['view' => $omr, 'create' => $om, 'edit' => $om, 'delete' => $om],
            'coupons' => ['view' => $omr, 'create' => $om, 'edit' => $om, 'delete' => $om],
            'expenses' => ['view' => $om, 'create' => $om, 'edit' => $om, 'delete' => $o],
            'cash_closings' => ['view' => $omr, 'create' => $omr, 'edit' => $o, 'delete' => $o],
            'attendance' => ['view' => $all, 'create' => $omr, 'edit' => $om, 'delete' => $om],
            'fitness' => ['view' => $omt, 'create' => $omt, 'edit' => $omt, 'delete' => $omt],
            'staff' => ['view' => $om, 'create' => $o, 'edit' => $o, 'delete' => $o],
            'staff_attendance' => ['view' => $om, 'create' => $om, 'edit' => $om, 'delete' => $om],
            'ratings' => ['view' => $omt, 'create' => [], 'edit' => [], 'delete' => $o],
            'branches' => ['view' => $o, 'create' => $o, 'edit' => $o, 'delete' => $o],
            'messaging' => ['view' => $om, 'create' => $om, 'edit' => $om, 'delete' => $om],
            'reports' => ['view' => $om],
            'audit' => ['view' => $o],
            'settings' => ['view' => $o, 'edit' => $o],
        ];
    }

    public static function can(string $ability, ?StaffRole $role = null): bool
    {
        $role ??= Tenancy::role();

        if (! $role) {
            return false;
        }

        [$area, $action] = explode('.', $ability, 2);

        return in_array($role, static::matrix()[$area][$action] ?? [], true);
    }

    public static function isTrainer(): bool
    {
        return Tenancy::role() === StaffRole::Trainer;
    }
}
