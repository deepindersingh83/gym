<?php

namespace App\Support;

use App\Enums\StaffRole;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Resolves the gym (tenant) and branch that the current request or job works in.
 *
 * Inside the gym panel the gym comes from Filament's tenant. Elsewhere (member
 * portal, console commands, queued jobs) it is set explicitly with setGym().
 */
class Tenancy
{
    protected static ?Gym $gym = null;

    /** @var array<string, ?GymUser> */
    protected static array $memberships = [];

    public static function setGym(?Gym $gym): void
    {
        static::$gym = $gym;
        static::$memberships = [];
    }

    public static function gym(): ?Gym
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Gym ? $tenant : static::$gym;
    }

    public static function gymId(): ?int
    {
        return static::gym()?->getKey();
    }

    /** Runs the callback with the given gym as the current tenant. */
    public static function forGym(Gym $gym, callable $callback): mixed
    {
        $previous = static::$gym;
        static::setGym($gym);

        try {
            return $callback($gym);
        } finally {
            static::setGym($previous);
        }
    }

    public static function membership(?User $user = null): ?GymUser
    {
        $user ??= auth()->user();
        $gym = static::gym();

        if (! $user instanceof User || ! $gym) {
            return null;
        }

        $key = $user->getKey().':'.$gym->getKey();

        return static::$memberships[$key] ??= GymUser::query()
            ->where('gym_id', $gym->getKey())
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();
    }

    public static function role(): ?StaffRole
    {
        return static::membership()?->role;
    }

    /**
     * The branch the current user is limited to, or null for "all branches".
     * Staff assigned to a branch only ever see that branch; owners can pick
     * one with the branch switcher.
     */
    public static function branchId(): ?int
    {
        $membership = static::membership();

        if (! $membership) {
            return null;
        }

        if (! $membership->role->seesAllBranches() && $membership->branch_id) {
            return $membership->branch_id;
        }

        $selected = session(static::branchSessionKey());

        return $selected ? (int) $selected : null;
    }

    public static function selectBranch(?int $branchId): void
    {
        session([static::branchSessionKey() => $branchId]);
    }

    public static function canSwitchBranch(): bool
    {
        $membership = static::membership();

        return $membership !== null && ($membership->role->seesAllBranches() || ! $membership->branch_id);
    }

    protected static function branchSessionKey(): string
    {
        return 'selected_branch.'.static::gymId();
    }
}
