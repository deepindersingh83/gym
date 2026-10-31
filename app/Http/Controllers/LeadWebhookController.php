<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Jobs\ImportMetaLead;
use App\Models\Gym;
use App\Services\LeadInbox;
use App\Services\MetaLeadAds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/** Incoming enquiries from Meta lead ads and from Zapier / Make / Pabbly-style webhooks. */
class LeadWebhookController extends Controller
{
    /** Meta checks the webhook URL once when it is set up in the app dashboard. */
    public function verifyMeta(Request $request): Response
    {
        $ok = $request->query('hub_mode') === 'subscribe'
            && filled(config('gym.meta.verify_token'))
            && hash_equals((string) config('gym.meta.verify_token'), (string) $request->query('hub_verify_token'));

        return $ok ? response((string) $request->query('hub_challenge')) : response('Forbidden', 403);
    }

    public function meta(Request $request, MetaLeadAds $meta): JsonResponse
    {
        abort_unless($meta->verifySignature($request->getContent(), $request->header('X-Hub-Signature-256')), 401);

        foreach ($request->json('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $gym = Gym::query()->where('facebook_page_id', (string) ($value['page_id'] ?? ''))->first();

                if (! $gym || blank($value['leadgen_id'] ?? null)) {
                    Log::info('Meta lead for an unknown page', ['page_id' => $value['page_id'] ?? null]);

                    continue;
                }

                ImportMetaLead::dispatch($gym->id, (string) $value['leadgen_id']);
            }
        }

        // Meta retries anything that is not a quick 200.
        return response()->json(['ok' => true]);
    }

    /** POST /webhooks/leads/{token} with name, phone, email and optional source / message. */
    public function generic(Request $request, string $token): JsonResponse
    {
        $gym = Gym::query()->where('lead_webhook_token', $token)->first();
        abort_unless($gym && $gym->hasActiveSaasSubscription(), 404);

        $data = $request->all();
        $name = Arr::first([$data['name'] ?? null, $data['full_name'] ?? null, trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''))], fn ($v) => filled($v));
        $phone = $data['phone'] ?? $data['phone_number'] ?? $data['mobile'] ?? null;
        $email = $data['email'] ?? null;

        if (blank($name) || (blank($phone) && blank($email))) {
            return response()->json(['error' => 'Send at least a name and a phone number or email.'], 422);
        }

        $lead = app(LeadInbox::class)->receive($gym, [
            'external_id' => filled($data['id'] ?? $data['lead_id'] ?? null) ? 'hook:'.($data['id'] ?? $data['lead_id']) : null,
            'name' => mb_substr((string) $name, 0, 120),
            'phone' => $phone ? mb_substr((string) $phone, 0, 20) : null,
            'email' => $email ? mb_substr((string) $email, 0, 120) : null,
            'source' => LeadSource::tryFrom(strtolower((string) ($data['source'] ?? ''))) ?? LeadSource::Other,
            'interest' => isset($data['interest']) ? mb_substr((string) $data['interest'], 0, 120) : null,
            'notes' => isset($data['message']) ? mb_substr((string) $data['message'], 0, 2000) : null,
            'raw' => $data,
        ]);

        return response()->json(['ok' => true, 'id' => $lead->id], $lead->wasRecentlyCreated ? 201 : 200);
    }
}
