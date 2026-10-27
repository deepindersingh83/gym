<?php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Services\Payments\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, Gym $gym): JsonResponse
    {
        $razorpay = RazorpayService::for($gym);

        abort_unless($razorpay->verifyWebhookSignature($request->getContent(), $request->header('X-Razorpay-Signature')), 401);

        $razorpay->handleWebhook($request->json()->all());

        return response()->json(['ok' => true]);
    }
}
