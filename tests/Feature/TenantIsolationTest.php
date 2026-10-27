<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Member;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_only_return_the_current_gyms_rows(): void
    {
        $gymA = $this->createGym(['name' => 'Gym A']);
        $gymB = $this->createGym(['name' => 'Gym B']);
        $this->createMember($gymA, ['name' => 'Alice']);
        $this->createMember($gymB, ['name' => 'Bob']);

        Tenancy::setGym($gymA);
        $this->assertSame(['Alice'], Member::query()->pluck('name')->all());

        Tenancy::setGym($gymB);
        $this->assertSame(['Bob'], Member::query()->pluck('name')->all());
    }

    public function test_new_rows_are_stamped_with_the_current_gym(): void
    {
        $gym = $this->createGym();
        Tenancy::setGym($gym);

        $member = Member::create(['name' => 'Chandra', 'phone' => '9811111111']);

        $this->assertSame($gym->id, $member->gym_id);
        $this->assertSame('M0001', $member->member_code);
    }

    public function test_owner_cannot_open_another_gyms_panel_or_records(): void
    {
        $gymA = $this->createGym(['name' => 'Gym A']);
        $gymB = $this->createGym(['name' => 'Gym B']);
        $memberB = $this->createMember($gymB);

        $this->actingAs($this->ownerOf($gymA));

        $this->get("/app/{$gymB->slug}")->assertNotFound();
        $this->get("/app/{$gymA->slug}/members/{$memberB->id}")->assertNotFound();
    }

    public function test_branch_staff_only_see_their_branch(): void
    {
        $gym = $this->createGym();
        $main = $gym->branches()->first();
        $other = $gym->branches()->create(['name' => 'Second']);
        $this->createMember($gym, ['name' => 'Main member', 'branch_id' => $main->id]);
        $this->createMember($gym, ['name' => 'Other member', 'branch_id' => $other->id]);
        $receptionist = $this->addStaff($gym, StaffRole::Receptionist, $main->id);

        $this->actingAs($receptionist);
        Tenancy::setGym($gym);

        $this->assertSame(['Main member'], Member::query()->pluck('name')->all());
    }

    public function test_trainers_only_see_members_assigned_to_them(): void
    {
        $gym = $this->createGym();
        $trainer = $this->addStaff($gym, StaffRole::Trainer);
        $this->createMember($gym, ['name' => 'Mine', 'trainer_id' => $trainer->id]);
        $this->createMember($gym, ['name' => 'Not mine']);

        $this->actingAs($trainer);
        Tenancy::setGym($gym);

        $this->assertSame(['Mine'], Member::query()->pluck('name')->all());
    }
}
