<?php

namespace Database\Seeders;

use App\Models\SaasPlan;
use App\Support\Modules;
use Illuminate\Database\Seeder;

/** The plans gym owners can buy. Prices and limits can be changed later in the admin panel. */
class SaasPlanSeeder extends Seeder
{
    public const AUD_PRICES = [
        'basic' => ['monthly_price_aud' => 29, 'yearly_price_aud' => 290],
        'pro' => ['monthly_price_aud' => 59, 'yearly_price_aud' => 590],
        'enterprise' => ['monthly_price_aud' => 149, 'yearly_price_aud' => 1490],
    ];

    public function run(): void
    {
        $plans = [
            ['slug' => 'basic', 'name' => 'Basic', 'monthly_price' => 999, 'yearly_price' => 9990, 'max_members' => 300, 'max_branches' => 1, 'max_staff' => 5, 'sort' => 1,
                'features' => ['Members & plans', 'Payments & GST invoices', 'Renewal reminders', 'Owner dashboard']],
            ['slug' => 'pro', 'name' => 'Pro', 'monthly_price' => 1999, 'yearly_price' => 19990, 'max_members' => 1500, 'max_branches' => 3, 'max_staff' => 20, 'sort' => 2,
                'features' => ['Everything in Basic', 'Multiple branches', 'Member app', 'UPI AutoPay', 'Broadcasts']],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'monthly_price' => 4999, 'yearly_price' => 49990, 'max_members' => null, 'max_branches' => null, 'max_staff' => null, 'sort' => 3,
                'features' => ['Everything in Pro', 'Unlimited members, branches and staff', 'Priority support']],
        ];

        foreach ($plans as $plan) {
            // Only create missing plans, so re-running never overwrites prices changed in /admin.
            SaasPlan::query()->firstOrCreate(['slug' => $plan['slug']], [
                ...$plan,
                'modules' => Modules::DEFAULTS[$plan['slug']] ?? null,
                ...self::AUD_PRICES[$plan['slug']],
            ]);
        }
    }
}
