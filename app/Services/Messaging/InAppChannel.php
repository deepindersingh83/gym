<?php

namespace App\Services\Messaging;

use App\Models\Member;
use Filament\Notifications\Notification;

/** Notification in the member app's bell menu (members who have a login). */
class InAppChannel extends Channel
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function send(Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params): void
    {
        if ($member->user) {
            Notification::make()->title($subject)->body($body)->sendToDatabase($member->user);
        }
    }
}
