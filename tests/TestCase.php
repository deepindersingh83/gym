<?php

namespace Tests;

use App\Enums\PlanType;
use App\Enums\StaffRole;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentMember;
use App\Support\Tenancy;
use Database\Seeders\SaasPlanSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Tenancy::setGym(null);
        CurrentMember::set(null);
    }

    protected function createGym(array $attributes = []): Gym
    {
        $this->seed(SaasPlanSeeder::class);

        $owner = User::factory()->create();
        $gym = Gym::create(['name' => 'Iron Temple', 'phone' => '9000000000', ...$attributes]);
        $gym->users()->attach($owner->id, ['role' => StaffRole::Owner->value]);

        return $gym;
    }

    protected function ownerOf(Gym $gym): User
    {
        return $gym->users()->wherePivot('role', StaffRole::Owner->value)->firstOrFail();
    }

    protected function addStaff(Gym $gym, StaffRole $role, ?int $branchId = null): User
    {
        $user = User::factory()->create();
        $gym->users()->attach($user->id, ['role' => $role->value, 'branch_id' => $branchId]);

        return $user;
    }

    protected function createPlan(Gym $gym, array $attributes = []): MembershipPlan
    {
        return MembershipPlan::withoutGlobalScopes()->create([
            'gym_id' => $gym->id,
            'name' => 'Monthly',
            'type' => PlanType::Monthly,
            'duration_value' => 1,
            'duration_unit' => 'month',
            'price' => 1000,
            'admission_fee' => 0,
            'gst_rate' => 18,
            ...$attributes,
        ]);
    }

    protected function createMember(Gym $gym, array $attributes = []): Member
    {
        static $phone = 9100000000;

        return Member::withoutGlobalScopes()->create([
            'gym_id' => $gym->id,
            'branch_id' => $gym->branches()->value('id'),
            'name' => fake()->name(),
            'phone' => (string) $phone++,
            ...$attributes,
        ]);
    }
}
