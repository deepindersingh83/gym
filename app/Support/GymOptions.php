<?php

namespace App\Support;

use App\Enums\StaffRole;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Collection;

/** Select options limited to the current gym. */
class GymOptions
{
    /** @return Collection<int, string> */
    public static function trainers(): Collection
    {
        return static::staff(StaffRole::Trainer);
    }

    /** @return Collection<int, string> */
    public static function staff(?StaffRole $role = null): Collection
    {
        return User::query()
            ->whereHas('gyms', function ($query) use ($role) {
                $query->whereKey(Tenancy::gymId())->where('gym_user.is_active', true);

                if ($role) {
                    $query->where('gym_user.role', $role->value);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public static function branches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public static function plans(): Collection
    {
        return MembershipPlan::query()->where('is_active', true)->orderBy('price')->get()
            ->mapWithKeys(fn (MembershipPlan $plan) => [$plan->id => "{$plan->name} — ".Money::format($plan->price)]);
    }

    /** @return Collection<int, string> */
    public static function usableCoupons(): Collection
    {
        return Coupon::query()->where('is_active', true)->get()
            ->filter->isUsable()
            ->mapWithKeys(fn (Coupon $coupon) => [
                $coupon->id => $coupon->code.' ('.($coupon->discount_type === 'percent' ? (float) $coupon->value.'%' : Money::format($coupon->value)).' off)',
            ]);
    }
}
