<?php

namespace App\Services\Messaging;

use App\Models\Member;
use App\Support\Country;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** MSG91 flow API (DLT-approved templates, as Indian SMS rules require). */
class SmsChannel extends Channel
{
    public function isConfigured(): bool
    {
        return filled($this->gym->integration('msg91_auth_key', 'gym.sms.msg91_auth_key'));
    }

    public function send(Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params): void
    {
        $flowId = $providerTemplate ?: config('gym.sms.msg91_flow_id');

        if (blank($flowId)) {
            throw new RuntimeException('SMS needs an approved MSG91 flow / DLT template. Add its ID to the message template.');
        }

        $variables = [];
        foreach (array_values($params) as $i => $value) {
            $variables['var'.($i + 1)] = $value;
        }

        Http::withHeaders(['authkey' => $this->gym->integration('msg91_auth_key', 'gym.sms.msg91_auth_key')])
            ->acceptJson()
            ->timeout(15)
            ->post('https://control.msg91.com/api/v5/flow', array_filter([
                'template_id' => $flowId,
                'sender' => $this->gym->integration('msg91_sender', 'gym.sms.msg91_sender'),
                'short_url' => '0',
                'recipients' => [['mobiles' => PhoneNumber::international($recipient, Country::get($this->gym->country, 'phone_code')), ...$variables]],
            ]))
            ->throw();
    }
}
