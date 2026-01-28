<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;

class PaymentService
{
    /**
     * Create Razorpay order.
     */
    public function createRazorpayOrder(Booking $booking): array
    {
        $keyId = SystemSetting::get('payments.razorpay_key_id');
        $keySecret = SystemSetting::get('payments.razorpay_key_secret');

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials not configured.');
        }

        // Decrypt if encrypted
        try {
            $keySecret = Crypt::decryptString($keySecret);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        $razorpay = new RazorpayApi($keyId, $keySecret);

        $orderData = [
            'receipt' => 'booking_' . $booking->id,
            'amount' => (int)($booking->total_amount * 100), // Convert to paise
            'currency' => $booking->ride->currency_code ?? 'INR',
            'notes' => [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
            ],
        ];

        $order = $razorpay->order->create($orderData);

        // Create payment record
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'razorpay',
            'provider_order_id' => $order['id'],
            'amount' => $booking->total_amount,
            'currency_code' => $booking->ride->currency_code ?? 'INR',
            'status' => 'initiated',
            'meta' => ['order' => $order],
        ]);

        return [
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'key_id' => $keyId,
        ];
    }

    /**
     * Verify Razorpay payment.
     */
    public function verifyRazorpayPayment(Booking $booking, string $razorpayPaymentId, string $razorpayOrderId, string $razorpaySignature): bool
    {
        $keyId = SystemSetting::get('payments.razorpay_key_id');
        $keySecret = SystemSetting::get('payments.razorpay_key_secret');

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials not configured.');
        }

        try {
            $keySecret = Crypt::decryptString($keySecret);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        $razorpay = new RazorpayApi($keyId, $keySecret);

        // Verify signature
        $attributes = [
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $razorpaySignature,
        ];

        try {
            $razorpay->utility->verifyPaymentSignature($attributes);
        } catch (\Exception $e) {
            Log::error('Razorpay signature verification failed: ' . $e->getMessage());
            return false;
        }

        // Get payment details
        $payment = $razorpay->payment->fetch($razorpayPaymentId);

        if ($payment['status'] !== 'captured' && $payment['status'] !== 'authorized') {
            return false;
        }

        // Update payment record
        $paymentRecord = $booking->payment ?? Payment::where('booking_id', $booking->id)->where('provider', 'razorpay')->first();
        if ($paymentRecord) {
            $paymentRecord->provider_payment_id = $razorpayPaymentId;
            $paymentRecord->status = 'paid';
            $paymentRecord->save();
            $paymentRecord->markPaid();
        } else {
            Payment::create([
                'booking_id' => $booking->id,
                'provider' => 'razorpay',
                'provider_payment_id' => $razorpayPaymentId,
                'provider_order_id' => $razorpayOrderId,
                'amount' => $booking->total_amount,
                'currency_code' => $booking->ride->currency_code ?? 'INR',
                'status' => 'paid',
            ])->markPaid();
        }

        return true;
    }

    /**
     * Create Stripe PaymentIntent.
     */
    public function createStripeIntent(Booking $booking): array
    {
        $secretKey = SystemSetting::get('payments.stripe_secret_key');

        if (!$secretKey) {
            throw new \Exception('Stripe credentials not configured.');
        }

        try {
            $secretKey = Crypt::decryptString($secretKey);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        Stripe::setApiKey($secretKey);

        $intent = PaymentIntent::create([
            'amount' => (int)($booking->total_amount * 100), // Convert to cents
            'currency' => strtolower($booking->ride->currency_code ?? 'usd'),
            'metadata' => [
                'booking_id' => $booking->id,
                'ride_id' => $booking->ride_id,
            ],
        ]);

        // Create payment record
        Payment::create([
            'booking_id' => $booking->id,
            'provider' => 'stripe',
            'provider_payment_id' => $intent->id,
            'amount' => $booking->total_amount,
            'currency_code' => $booking->ride->currency_code ?? 'USD',
            'status' => 'initiated',
            'meta' => ['intent' => $intent->toArray()],
        ]);

        $publishableKey = SystemSetting::get('payments.stripe_publishable_key');
        try {
            $publishableKey = Crypt::decryptString($publishableKey);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        return [
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'publishable_key' => $publishableKey,
        ];
    }

    /**
     * Confirm Stripe payment.
     */
    public function confirmStripePayment(Booking $booking, string $paymentIntentId): bool
    {
        $secretKey = SystemSetting::get('payments.stripe_secret_key');

        if (!$secretKey) {
            throw new \Exception('Stripe credentials not configured.');
        }

        try {
            $secretKey = Crypt::decryptString($secretKey);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        Stripe::setApiKey($secretKey);

        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);

            if ($intent->status !== 'succeeded') {
                return false;
            }

            // Update payment record
            $paymentRecord = $booking->payment ?? Payment::where('booking_id', $booking->id)->where('provider', 'stripe')->first();
            if ($paymentRecord) {
                $paymentRecord->provider_payment_id = $paymentIntentId;
                $paymentRecord->status = 'paid';
                $paymentRecord->save();
                $paymentRecord->markPaid();
            } else {
                Payment::create([
                    'booking_id' => $booking->id,
                    'provider' => 'stripe',
                    'provider_payment_id' => $paymentIntentId,
                    'amount' => $booking->total_amount,
                    'currency_code' => $booking->ride->currency_code ?? 'USD',
                    'status' => 'paid',
                ])->markPaid();
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe payment confirmation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process refund.
     */
    public function processRefund(Booking $booking, ?string $reason = null): bool
    {
        $payment = $booking->payment;

        if (!$payment || $payment->status !== 'paid') {
            return false;
        }

        $refundPolicy = SystemSetting::get('bookings.refund_policy', 'none');
        if ($refundPolicy === 'none') {
            return false;
        }

        $refundAmount = $booking->total_amount;
        if ($refundPolicy === 'partial') {
            $partialPercent = SystemSetting::get('bookings.refund_partial_percent', 50);
            $refundAmount = ($booking->total_amount * $partialPercent) / 100;
        }

        try {
            if ($payment->provider === 'razorpay') {
                return $this->refundRazorpay($payment, $refundAmount, $reason);
            } elseif ($payment->provider === 'stripe') {
                return $this->refundStripe($payment, $refundAmount, $reason);
            }
        } catch (\Exception $e) {
            Log::error('Refund processing failed: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Refund Razorpay payment.
     */
    protected function refundRazorpay(Payment $payment, float $amount, ?string $reason = null): bool
    {
        $keyId = SystemSetting::get('payments.razorpay_key_id');
        $keySecret = SystemSetting::get('payments.razorpay_key_secret');

        try {
            $keySecret = Crypt::decryptString($keySecret);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        $razorpay = new RazorpayApi($keyId, $keySecret);

        $refund = $razorpay->payment->fetch($payment->provider_payment_id)->refund([
            'amount' => (int)($amount * 100),
            'notes' => ['reason' => $reason ?? 'Booking cancellation'],
        ]);

        $payment->markRefunded();
        
        // Process wallet refund if booking was completed
        if ($payment->booking->status === 'completed') {
            try {
                $walletService = app(\App\Services\WalletService::class);
                $walletService->processBookingRefund($payment->booking);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to process wallet refund', ['error' => $e->getMessage()]);
            }
        }
        
        return true;
    }

    /**
     * Refund Stripe payment.
     */
    protected function refundStripe(Payment $payment, float $amount, ?string $reason = null): bool
    {
        $secretKey = SystemSetting::get('payments.stripe_secret_key');

        try {
            $secretKey = Crypt::decryptString($secretKey);
        } catch (\Exception $e) {
            // Assume not encrypted
        }

        Stripe::setApiKey($secretKey);

        $refund = Refund::create([
            'payment_intent' => $payment->provider_payment_id,
            'amount' => (int)($amount * 100),
            'reason' => 'requested_by_customer',
            'metadata' => ['reason' => $reason ?? 'Booking cancellation'],
        ]);

        $payment->markRefunded();
        
        // Process wallet refund if booking was completed
        if ($payment->booking->status === 'completed') {
            try {
                $walletService = app(\App\Services\WalletService::class);
                $walletService->processBookingRefund($payment->booking);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to process wallet refund', ['error' => $e->getMessage()]);
            }
        }
        
        return true;
    }
}

