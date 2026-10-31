<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Models\Gym;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Facebook / Instagram lead ads through the Meta Graph API. */
class MetaLeadAds
{
    public function verifySignature(string $payload, ?string $header): bool
    {
        $secret = config('gym.meta.app_secret');

        return filled($secret) && filled($header)
            && hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), $header);
    }

    /** Fetches a submitted lead form and stores it as an enquiry. */
    public function import(Gym $gym, string $leadgenId): Lead
    {
        $token = $gym->integration('facebook_page_token', 'gym.meta.none');

        if (blank($token)) {
            throw new RuntimeException("Gym {$gym->id} has no Facebook page token.");
        }

        $lead = $this->graph()->get($leadgenId, [
            'fields' => 'field_data,created_time,platform,ad_name,form_id,campaign_name',
            'access_token' => $token,
        ])->throw()->json();

        $fields = collect($lead['field_data'] ?? [])->mapWithKeys(fn ($f) => [strtolower($f['name']) => implode(', ', $f['values'] ?? [])]);
        $name = $fields->get('full_name') ?: trim($fields->get('first_name').' '.$fields->get('last_name')) ?: 'Lead '.$leadgenId;
        $known = ['full_name', 'first_name', 'last_name', 'phone_number', 'phone', 'email'];
        $extra = $fields->except($known)->map(fn ($v, $k) => str($k)->replace('_', ' ')->ucfirst().': '.$v)->implode("\n");

        return app(LeadInbox::class)->receive($gym, [
            'external_id' => 'meta:'.$leadgenId,
            'name' => $name,
            'phone' => $fields->get('phone_number') ?: $fields->get('phone'),
            'email' => $fields->get('email'),
            'source' => ($lead['platform'] ?? '') === 'ig' ? LeadSource::Instagram : LeadSource::Facebook,
            'interest' => $lead['ad_name'] ?? $lead['campaign_name'] ?? null,
            'notes' => $extra ?: null,
            'raw' => $lead,
        ], 'New lead from '.(($lead['platform'] ?? '') === 'ig' ? 'Instagram' : 'Facebook'));
    }

    /** Subscribes the gym's page to the platform app so its leads arrive here. */
    public function connectPage(Gym $gym): void
    {
        if (blank($gym->facebook_page_id) || blank($gym->integration('facebook_page_token', 'gym.meta.none'))) {
            throw new RuntimeException('Enter the Facebook page ID and page access token, then save first.');
        }

        $response = $this->graph()->post("{$gym->facebook_page_id}/subscribed_apps", [
            'subscribed_fields' => 'leadgen',
            'access_token' => $gym->integration('facebook_page_token', 'gym.meta.none'),
        ]);

        if (! $response->successful() || ! $response->json('success')) {
            throw new RuntimeException($response->json('error.message') ?? 'Facebook did not accept the connection.');
        }
    }

    protected function graph()
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('gym.meta.graph_version').'/')->acceptJson()->timeout(20);
    }
}
