<?php

namespace App\Http\Controllers;

use App\Filament\Gym\Resources\Members\MemberResource;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Payments\RazorpayService;
use App\Support\Height;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** A gym's public page where new customers sign up (and optionally pay online). */
class JoinController extends Controller
{
    public function show(Gym $gym): View
    {
        Tenancy::setGym($gym);

        return view('public.join', [
            'gym' => $gym,
            'plans' => MembershipPlan::query()->where('is_active', true)->orderBy('price')->get(),
            'open' => $this->acceptingSignups($gym),
            'canPayOnline' => RazorpayService::for($gym)->isConfigured(),
            'healthQuestions' => MemberResource::HEALTH_QUESTIONS,
            'goals' => MemberResource::GOALS,
            'heightInFeet' => Height::usesFeet($gym),
        ]);
    }

    public function store(Request $request, Gym $gym): RedirectResponse
    {
        Tenancy::setGym($gym);
        abort_unless($this->acceptingSignups($gym), 403, 'Online signup is closed for this gym.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('members', 'phone')->where('gym_id', $gym->id)->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'height_cm' => ['nullable', 'numeric', 'between:50,250'],
            'height_feet' => ['nullable', 'integer', 'between:1,8'],
            'height_inches' => ['nullable', 'numeric', 'between:0,11.9'],
            'weight_kg' => ['nullable', 'numeric', 'between:10,400'],
            'goal' => ['nullable', Rule::in(array_keys(MemberResource::GOALS))],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'health_questionnaire' => ['nullable', 'array'],
            'health_questionnaire.*' => [Rule::in(array_keys(MemberResource::HEALTH_QUESTIONS))],
            'medical_conditions' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'plan_id' => ['nullable', Rule::exists('membership_plans', 'id')->where('gym_id', $gym->id)->where('is_active', true)],
            'consent' => ['accepted'],
        ], ['phone.unique' => 'This phone number is already registered with the gym. Please contact the front desk.']);

        // Gyms that measure in feet send feet and inches; store centimetres.
        if (filled($data['height_feet'] ?? null) || filled($data['height_inches'] ?? null)) {
            $data['height_cm'] = Height::fromFeet($data['height_feet'] ?? 0, $data['height_inches'] ?? 0);
        }
        unset($data['height_feet'], $data['height_inches']);

        $existing = User::query()->where('email', strtolower($data['email']))->first();

        if ($existing && ! Hash::check($data['password'], $existing->password)) {
            throw ValidationException::withMessages(['email' => 'This email already has an account. Enter that account\'s password, or use another email.']);
        }

        $member = DB::transaction(function () use ($data, $existing, $gym, $request) {
            $user = $existing ?? User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);

            return Member::create([
                'gym_id' => $gym->id,
                'branch_id' => $gym->branches()->where('is_active', true)->orderBy('id')->value('id'),
                'user_id' => $user->id,
                'photo_path' => $request->file('photo')?->store("members/{$gym->id}", 'public'),
                'source' => 'online',
                ...collect($data)->except(['password', 'photo', 'plan_id', 'consent'])->all(),
            ]);
        });

        Auth::login($member->user);

        if (! empty($data['plan_id']) && RazorpayService::for($gym)->isConfigured()) {
            try {
                return redirect()->away(RazorpayService::for($gym)->paymentLinkForRenewal($member, MembershipPlan::query()->findOrFail($data['plan_id'])));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('join.welcome', $gym);
    }

    public function welcome(Gym $gym): View
    {
        return view('public.welcome-member', ['gym' => $gym]);
    }

    protected function acceptingSignups(Gym $gym): bool
    {
        return $gym->setting('allow_online_signup') && $gym->hasActiveSaasSubscription() && $gym->withinLimit('members');
    }
}
