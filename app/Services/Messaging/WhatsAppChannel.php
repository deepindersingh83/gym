<?php

namespace App\Services\Messaging;

use App\Models\Member;
use App\Support\Country;
use Illuminate\Support\Facades\Http;

/** Meta WhatsApp Cloud API. */
class WhatsAppChannel extends Channel
{
    public function isConfigured(): bool
    {
        return filled($this->token()) && filled($this->phoneNumberId());
    }

    public function send(Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params): void
    {
        // Business-initiated messages need an approved template; free text only works
        // within 24 hours of the member last messaging the gym.
        $message = $providerTemplate
            ? ['type' => 'template', 'template' => [
                'name' => $providerTemplate,
                'language' => ['code' => 'en'],
                'components' => $params ? [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $params)]] : [],
            ]]
            : ['type' => 'text', 'text' => ['body' => $body, 'preview_url' => false]];

        Http::withToken($this->token())
            ->timeout(15)
            ->post(sprintf('https://graph.facebook.com/%s/%s/messages', config('gym.whatsapp.api_version'), $this->phoneNumberId()), [
                'messaging_product' => 'whatsapp',
                'to' => PhoneNumber::international($recipient, Country::get($this->gym->country, 'phone_code')),
                ...$message,
            ])
            ->throw();
    }

    protected function token(): ?string
    {
        return $this->gym->integration('whatsapp_token', 'gym.whatsapp.token');
    }

    protected function phoneNumberId(): ?string
    {
        return $this->gym->integration('whatsapp_phone_number_id', 'gym.whatsapp.phone_number_id');
    }
}
