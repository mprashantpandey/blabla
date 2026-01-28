<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemSetting;
use App\Services\PaymentService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Razorpay\Api\Api as RazorpayApi;
use Stripe\Stripe as StripeSDK;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    protected PaymentService $paymentService;
    protected BookingService $bookingService;

    public function __construct(PaymentService $paymentService, BookingService $bookingService)
    {
        $this->paymentService = $paymentService;
        $this->bookingService = $bookingService;
    }

    /**
     * Handle Razorpay webhook.
     */
    public function razorpay(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;

        Log::info('Razorpay webhook received', ['event' => $event, 'payload' => $payload]);

        if ($event === 'payment.captured') {
            $paymentId = $payload['payload']['payment']['entity']['id'] ?? null;
            $orderId = $payload['payload']['payment']['entity']['order_id'] ?? null;

            if ($paymentId && $orderId) {
                $payment = \App\Models\Payment::where('provider', 'razorpay')
                    ->where('provider_order_id', $orderId)
                    ->first();

                if ($payment && $payment->status !== 'paid') {
                    $payment->provider_payment_id = $paymentId;
                    $payment->status = 'paid';
                    $payment->save();
                    $payment->markPaid();

                    $booking = $payment->booking;
                    if ($booking) {
                        $this->bookingService->confirmBooking($booking);
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle Stripe webhook.
     */
    public function stripe(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = SystemSetting::get('payments.stripe_webhook_secret');

        if (!$endpointSecret) {
            Log::warning('Stripe webhook secret not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $paymentIntentId = $paymentIntent->id;

            $payment = \App\Models\Payment::where('provider', 'stripe')
                ->where('provider_payment_id', $paymentIntentId)
                ->first();

            if ($payment && $payment->status !== 'paid') {
                $payment->status = 'paid';
                $payment->save();
                $payment->markPaid();

                $booking = $payment->booking;
                if ($booking) {
                    $this->bookingService->confirmBooking($booking);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
