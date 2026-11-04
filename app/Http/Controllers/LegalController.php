<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use Illuminate\Contracts\View\View;

/** Platform privacy policy and terms, and each gym's privacy notice for its members. */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('public.legal.privacy', $this->platform());
    }

    public function terms(): View
    {
        return view('public.legal.terms', $this->platform());
    }

    public function gymPrivacy(Gym $gym): View
    {
        return view('public.legal.gym-privacy', $this->platform() + [
            'gym' => $gym,
            'brand' => $gym->primary_color ?? '#f59e0b',
            'isAustralia' => $gym->country === 'AU',
        ]);
    }

    protected function platform(): array
    {
        $india = (array) config('gym.billing.entities.IN');
        $australia = (array) config('gym.billing.entities.AU');

        return [
            'app' => config('app.name'),
            'india' => $india + ['legal_name' => null],
            'australia' => $australia + ['legal_name' => null],
            'company' => $india['legal_name'] ?? $australia['legal_name'] ?? config('app.name'),
            'contact' => config('gym.legal.privacy_email') ?: config('gym.support.email') ?: config('mail.from.address'),
            'grievanceName' => config('gym.legal.grievance_officer'),
            'updated' => config('gym.legal.updated_on') ?: '4 November 2026',
        ];
    }
}
