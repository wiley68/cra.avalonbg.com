<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeBilling,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripeBilling->constructEvent($payload, $signature);
        } catch (SignatureVerificationException | UnexpectedValueException) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }

        $this->stripeBilling->handleEvent($event);

        return response()->json(['received' => true]);
    }
}
