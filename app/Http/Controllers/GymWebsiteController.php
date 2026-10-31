<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\StaffRole;
use App\Filament\Gym\Resources\Leads\LeadResource;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Lead;
use App\Models\MembershipPlan;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Each gym's public, search-friendly website with free-trial booking. */
class GymWebsiteController extends Controller
{
    public function show(Gym $gym): View
    {
        abort_unless($this->isPublic($gym), 404);
        Tenancy::setGym($gym);

        return view('public.gym-website', [
            'gym' => $gym,
            'plans' => $gym->websiteSetting('show_plans', true)
                ? MembershipPlan::query()->where('is_active', true)->orderBy('price')->get()
                : collect(),
            'trainers' => $gym->websiteSetting('show_trainers', true)
                ? GymUser::query()->with('user')->where('show_on_website', true)->where('is_active', true)->orderBy('id')->get()
                : collect(),
            'gallery' => collect($gym->websiteSetting('gallery', []))->map(fn ($path) => Storage::disk('public')->url($path)),
            'cover' => ($cover = $gym->websiteSetting('cover')) ? Storage::disk('public')->url($cover) : null,
            'canonical' => $gym->websiteUrl(),
        ]);
    }

    public function bookTrial(Request $request, Gym $gym): RedirectResponse
    {
        abort_unless($this->isPublic($gym), 404);

        // Bots fill in every field, including this hidden one.
        if (filled($request->input('website_url'))) {
            return back()->with('trial_booked', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'trial_date' => ['required', 'date', 'after_or_equal:today', 'before:+3 months'],
            'trial_time' => ['required', 'in:Morning,Afternoon,Evening'],
            'interest' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = Lead::withoutGlobalScopes()->create([
            'gym_id' => $gym->id,
            'branch_id' => $gym->branches()->where('is_active', true)->orderBy('id')->value('id'),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'source' => LeadSource::Website,
            'status' => LeadStatus::TrialBooked,
            'trial_date' => $data['trial_date'],
            'trial_time' => $data['trial_time'],
            'interest' => $data['interest'] ?? null,
            'notes' => $data['message'] ?? null,
        ]);

        $this->notifyStaff($gym, $lead);

        return redirect()->to(url()->previous().'#trial')->with('trial_booked', true);
    }

    public function sitemap(): Response
    {
        $gyms = Gym::query()->get()->filter(fn (Gym $gym) => $this->isPublic($gym));

        return response()
            ->view('public.sitemap', ['gyms' => $gyms])
            ->header('Content-Type', 'application/xml');
    }

    protected function isPublic(Gym $gym): bool
    {
        return $gym->hasActiveSaasSubscription() && $gym->websiteSetting('enabled', true);
    }

    /** Bell notification for the people who handle enquiries. */
    protected function notifyStaff(Gym $gym, Lead $lead): void
    {
        $recipients = $gym->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', [StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value])
            ->get()
            ->filter(fn ($user) => $user->pivot->role === StaffRole::Owner || ! $user->pivot->branch_id || $user->pivot->branch_id === $lead->branch_id);

        Notification::make()
            ->title('Free trial booked: '.$lead->name)
            ->body($lead->trial_date->format('D, d M').' · '.$lead->trial_time.' · '.$lead->phone)
            ->icon('heroicon-o-calendar-days')
            ->actions([Action::make('open')->label('View enquiries')->url(LeadResource::getUrl('index', tenant: $gym, panel: 'gym'))])
            ->sendToDatabase($recipients);
    }
}
