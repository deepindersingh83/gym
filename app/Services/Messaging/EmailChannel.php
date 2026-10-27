<?php

namespace App\Services\Messaging;

use App\Models\Member;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class EmailChannel extends Channel
{
    public function isConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    public function send(Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params): void
    {
        Mail::raw($body, function (Message $message) use ($recipient, $subject) {
            $message->to($recipient)->subject($subject);

            if ($this->gym->email) {
                $message->replyTo($this->gym->email, $this->gym->name);
            }
        });
    }
}
