<?php

namespace App\Http\Controllers;

use App\Services\Billing\BillingCheckout;
use App\Services\Billing\Stripe;
use App\Services\Payments\PlatformBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Webhooks for payments gyms make to the platform: Razorpay (India) and Stripe (Australia). */
class PlatformWebhookController extends Controller
{
    public function __invoke(Request $request, PlatformBilling $razorpay, BillingCheckout $checkout): JsonResponse
    {
        abort_unless($razorpay->verifyWebhookSignature($request->getContent(), $request->header('X-Razorpay-Signature')), 401);

        $checkout->handleRazorpay($request->json()->all());

        return response()->json(['ok' => true]);
    }

    public function stripe(Request $request, Stripe $stripe, BillingCheckout $checkout): JsonResponse
    {
        abort_unless($stripe->verifyWebhook($request->getContent(), $request->header('Stripe-Signature')), 401);

        $checkout->handleStripe(json_decode($request->getContent(), true) ?? []);

        return response()->json(['received' => true]);
    }
}
