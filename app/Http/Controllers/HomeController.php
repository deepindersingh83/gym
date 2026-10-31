<?php

namespace App\Http\Controllers;

use App\Models\SaasPlan;
use App\Services\CustomDomains;
use Illuminate\View\View;

/** Marketing home page with live software-plan pricing. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        // On a gym's own domain the home page is that gym's website.
        if ($gym = CustomDomains::current()) {
            return app(GymWebsiteController::class)->show($gym);
        }

        return view('welcome', [
            // Before the first migration the table does not exist yet; show the page without prices.
            'plans' => rescue(fn () => SaasPlan::query()->where('is_active', true)->orderBy('sort')->get(), collect(), report: false),
            'trialDays' => (int) config('gym.trial_days'),
        ]);
    }
}
