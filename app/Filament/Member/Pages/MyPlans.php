<?php

namespace App\Filament\Member\Pages;

use App\Filament\Gym\Resources\WorkoutPlans\WorkoutPlanResource;
use App\Models\DietPlan;
use App\Models\WorkoutPlan;
use App\Support\CurrentMember;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class MyPlans extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?string $title = 'Workout & diet';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.member.my-plans';

    protected function getViewData(): array
    {
        $memberId = CurrentMember::get()->id;

        return [
            'workouts' => WorkoutPlan::query()->where('member_id', $memberId)->where('is_active', true)->with('trainer')->latest()->get(),
            'diets' => DietPlan::query()->where('member_id', $memberId)->where('is_active', true)->with('trainer')->latest()->get(),
            'days' => WorkoutPlanResource::DAYS,
        ];
    }
}
