<?php

namespace App\Http\Controllers;

use App\Services\Payments\PlatformBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Razorpay webhooks for payments made to the platform itself (message credit packs). */
class PlatformWebhookController extends Controller
{
    public function __invoke(Request $request, PlatformBilling $billing): JsonResponse
    {
        abort_unless($billing->verifyWebhookSignature($request->getContent(), $request->header('X-Razorpay-Signature')), 401);

        $billing->handleWebhook($request->json()->all());

        return response()->json(['ok' => true]);
    }
}
