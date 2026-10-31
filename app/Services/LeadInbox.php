<?php

namespace App\Services;

use App\Enums\StaffRole;
use App\Filament\Gym\Resources\Leads\LeadResource;
use App\Models\Gym;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Single entry point for enquiries arriving from outside the panel (website
 * trial bookings, Facebook / Instagram lead ads, webhooks): stores the lead
 * once and tells the gym's staff.
 */
class LeadInbox
{
    /** @param  array<string, mixed>  $attributes  Lead columns; "external_id" de-duplicates repeats. */
    public function receive(Gym $gym, array $attributes, string $headline = 'New enquiry'): Lead
    {
        $attributes['branch_id'] ??= $gym->branches()->where('is_active', true)->orderBy('id')->value('id');

        $lead = filled($attributes['external_id'] ?? null)
            ? Lead::withoutGlobalScopes()->firstOrCreate(
                ['gym_id' => $gym->id, 'external_id' => $attributes['external_id']],
                $attributes,
            )
            : Lead::withoutGlobalScopes()->create(['gym_id' => $gym->id, ...$attributes]);

        if ($lead->wasRecentlyCreated) {
            $this->notifyStaff($gym, $lead, $headline);
        }

        return $lead;
    }

    /** Bell notification for owners, plus managers and reception of the lead's branch. */
    protected function notifyStaff(Gym $gym, Lead $lead, string $headline): void
    {
        $recipients = $gym->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', [StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value])
            ->get()
            ->filter(fn ($user) => $user->pivot->role === StaffRole::Owner || ! $user->pivot->branch_id || $user->pivot->branch_id === $lead->branch_id);

        $details = collect([
            $lead->source?->getLabel(),
            $lead->trial_date ? $lead->trial_date->format('D, d M').($lead->trial_time ? ' · '.$lead->trial_time : '') : null,
            $lead->phone,
        ])->filter()->implode(' · ');

        Notification::make()
            ->title($headline.': '.$lead->name)
            ->body($details)
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([Action::make('open')->label('View enquiries')->url(LeadResource::getUrl('index', tenant: $gym, panel: 'gym'))])
            ->sendToDatabase($recipients);
    }
}
