<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\PlanType;
use App\Enums\StaffRole;
use App\Filament\Gym\Schemas\GymDetailsFields;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\Gym;
use App\Models\Measurement;
use App\Models\Member;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Demo data: php artisan db:seed --class=DemoSeeder
 *
 * Logins (password "password"): admin@example.com (super admin), owner@example.com,
 * reception@example.com, trainer@example.com, member@example.com (member app).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SaasPlanSeeder::class);

        User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Platform Admin', 'password' => 'password'])
            ->forceFill(['is_super_admin' => true])->save();

        $owner = User::query()->firstOrCreate(['email' => 'owner@example.com'], ['name' => 'Ravi Sharma', 'phone' => '9876500001', 'password' => 'password']);
        $reception = User::query()->firstOrCreate(['email' => 'reception@example.com'], ['name' => 'Priya Nair', 'phone' => '9876500002', 'password' => 'password']);
        $trainer = User::query()->firstOrCreate(['email' => 'trainer@example.com'], ['name' => 'Arjun Mehta', 'phone' => '9876500003', 'password' => 'password']);

        $gym = Gym::query()->create([
            'name' => 'FitZone Gym',
            'slug' => Gym::uniqueSlug('fitzone'),
            'phone' => '9876543210',
            'email' => 'hello@fitzone.example',
            'address' => '12 MG Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => '411001',
            'gstin' => '27ABCDE1234F1Z5',
            'opening_hours' => GymDetailsFields::defaultHours(),
            'onboarding_completed' => true,
        ]);
        $main = $gym->branches()->first();
        $second = $gym->branches()->create(['name' => 'Koregaon Park', 'address' => '45 North Main Road']);

        $gym->users()->attach($owner->id, ['role' => StaffRole::Owner->value]);
        $gym->users()->attach($reception->id, ['role' => StaffRole::Receptionist->value, 'branch_id' => $main->id]);
        $gym->users()->attach($trainer->id, [
            'role' => StaffRole::Trainer->value, 'branch_id' => $main->id, 'designation' => 'Head coach',
            'show_on_website' => true, 'specialities' => 'Strength, fat loss, mobility',
            'bio' => '10 years of coaching beginners and athletes. Certified strength and conditioning coach.',
        ]);
        $gym->update(['website' => [
            'enabled' => true,
            'tagline' => 'Pune\'s friendliest strength & fitness gym',
            'about' => "FitZone is a 6,000 sq ft gym in the heart of Pune with free weights, cardio, a functional training zone and certified coaches.\n\nWhether it is your first day in a gym or you are chasing a personal best, we will help you get there.",
            'show_trainers' => true,
            'show_plans' => true,
        ]]);

        Tenancy::forGym($gym, function (Gym $gym) use ($main, $second, $trainer, $owner) {
            auth()->setUser($owner);

            $plans = collect([
                ['Monthly', PlanType::Monthly, 1000],
                ['Quarterly', PlanType::Quarterly, 2700],
                ['Half-yearly', PlanType::HalfYearly, 5000],
                ['Yearly', PlanType::Yearly, 9000],
                ['Day pass', PlanType::DayPass, 150],
                ['PT 12 sessions', PlanType::PersonalTraining, 6000],
                ['Couple yearly', PlanType::Couple, 16000],
            ])->map(function ($row) use ($gym) {
                [$value, $unit, $persons] = $row[1]->defaults();

                return $gym->membershipPlans()->create([
                    'name' => $row[0], 'type' => $row[1], 'duration_value' => $value, 'duration_unit' => $unit,
                    'max_persons' => $persons, 'price' => $row[2], 'admission_fee' => $row[1] === PlanType::DayPass ? 0 : 500,
                    'pt_sessions' => $row[1] === PlanType::PersonalTraining ? 12 : null,
                ]);
            });

            Coupon::create(['code' => 'DIWALI20', 'category' => 'festival', 'discount_type' => 'percent', 'value' => 20, 'valid_until' => today()->addMonth()]);
            Coupon::create(['code' => 'FRIEND500', 'category' => 'referral', 'discount_type' => 'fixed', 'value' => 500]);

            $service = app(SubscriptionService::class);
            $names = ['Aarav Patel', 'Diya Singh', 'Kabir Rao', 'Ananya Iyer', 'Vihaan Gupta', 'Isha Kulkarni', 'Rohan Desai', 'Meera Joshi', 'Aditya Verma', 'Sneha Pillai', 'Karan Malhotra', 'Pooja Reddy'];

            foreach ($names as $i => $name) {
                $member = Member::create([
                    'branch_id' => $i % 3 === 0 ? $second->id : $main->id,
                    'trainer_id' => $i % 2 === 0 ? $trainer->id : null,
                    'name' => $name,
                    'phone' => '98220'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'email' => $i === 0 ? 'member@example.com' : null,
                    'gender' => $i % 2 ? 'female' : 'male',
                    'date_of_birth' => today()->subYears(22 + $i)->addDays($i === 1 ? 0 : $i * 11),
                    'height_cm' => 160 + $i,
                    'goal' => ['weight_loss', 'muscle_gain', 'general_fitness'][$i % 3],
                    'joined_on' => today()->subMonths(12 - $i),
                ]);

                $plan = $plans[[0, 1, 3, 0, 2, 1, 0, 3, 1, 0, 2, 0][$i]];
                $start = today()->subDays([20, 85, 200, 33, 150, 10, 28, 360, 88, 45, 170, 3][$i]);
                $subscription = $service->create($member, $plan, $start);
                $total = (float) $subscription->total_amount;
                $paid = $i % 4 === 1 ? round($total / 2) : $total;
                $service->recordPayment($subscription, $paid, [PaymentMethod::Cash, PaymentMethod::GPay, PaymentMethod::Upi][$i % 3], null, ['paid_at' => $start->copy()->setTime(10, 30)]);

                foreach (range(0, 3) as $m) {
                    Measurement::create([
                        'member_id' => $member->id,
                        'recorded_on' => today()->subMonths(3 - $m),
                        'weight_kg' => 82 - $i - $m * 1.5,
                        'body_fat_pct' => 26 - $m,
                        'waist_cm' => 92 - $m * 2,
                    ]);
                }
            }

            $service->expireLapsed();

            $first = Member::query()->where('email', 'member@example.com')->first();
            $first->update(['user_id' => User::query()->firstOrCreate(['email' => 'member@example.com'], ['name' => $first->name, 'phone' => $first->phone, 'password' => 'password'])->id]);

            WorkoutPlan::create([
                'member_id' => $first->id, 'trainer_id' => $trainer->id, 'title' => 'Beginner full body', 'starts_on' => today(),
                'exercises' => [
                    ['day' => 'mon', 'exercise' => 'Squats', 'sets' => 3, 'reps' => '12', 'rest' => '60s'],
                    ['day' => 'mon', 'exercise' => 'Push-ups', 'sets' => 3, 'reps' => '10', 'rest' => '60s'],
                    ['day' => 'wed', 'exercise' => 'Deadlift', 'sets' => 3, 'reps' => '8', 'rest' => '90s'],
                    ['day' => 'fri', 'exercise' => 'Treadmill', 'sets' => 1, 'reps' => '20 min', 'rest' => '—'],
                ],
            ]);

            foreach ([[ExpenseCategory::Rent, 45000, 1], [ExpenseCategory::Salary, 60000, 1], [ExpenseCategory::Electricity, 8200, 5], [ExpenseCategory::Equipment, 15000, 12]] as [$category, $amount, $day]) {
                Expense::create(['branch_id' => $main->id, 'category' => $category, 'amount' => $amount, 'spent_on' => today()->startOfMonth()->addDays($day - 1)->min(today()), 'method' => 'bank_transfer']);
            }

            auth()->logout();
        });
    }
}
