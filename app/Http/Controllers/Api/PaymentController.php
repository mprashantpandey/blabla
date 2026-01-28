<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payment\VerifyRazorpayRequest;
use App\Http\Requests\Payment\ConfirmStripeRequest;
use App\Models\Booking;
use App\Services\PaymentService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    protected PaymentService $paymentService;
    protected BookingService $bookingService;

    public function __construct(PaymentService $paymentService, BookingService $bookingService)
    {
        $this->paymentService = $paymentService;
        $this->bookingService = $bookingService;
    }

    /**
     * Create Razorpay order.
     */
    public function createRazorpayOrder(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $user = $request->user();
        $booking = Booking::findOrFail($request->booking_id);

        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        if ($booking->payment_method !== 'razorpay') {
            return $this->error('Invalid payment method');
        }

        try {
            $orderData = $this->paymentService->createRazorpayOrder($booking);

            return $this->success($orderData, 'Razorpay order created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create order: ' . $e->getMessage());
        }
    }

    /**
     * Verify Razorpay payment.
     */
    public function verifyRazorpay(VerifyRazorpayRequest $request): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::findOrFail($request->booking_id);

        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        try {
            $verified = $this->paymentService->verifyRazorpayPayment(
                $booking,
                $request->razorpay_payment_id,
                $request->razorpay_order_id,
                $request->razorpay_signature
            );

            if (!$verified) {
                return $this->error('Payment verification failed');
            }

            // Confirm booking
            $this->bookingService->confirmBooking($booking);

            return $this->success([
                'booking' => $booking->fresh(),
                'payment_status' => 'paid',
            ], 'Payment verified and booking confirmed');
        } catch (\Exception $e) {
            return $this->error('Payment verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create Stripe PaymentIntent.
     */
    public function createStripeIntent(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $user = $request->user();
        $booking = Booking::findOrFail($request->booking_id);

        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        if ($booking->payment_method !== 'stripe') {
            return $this->error('Invalid payment method');
        }

        try {
            $intentData = $this->paymentService->createStripeIntent($booking);

            return $this->success($intentData, 'Stripe PaymentIntent created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create PaymentIntent: ' . $e->getMessage());
        }
    }

    /**
     * Confirm Stripe payment.
     */
    public function confirmStripe(ConfirmStripeRequest $request): JsonResponse
    {
        $user = $request->user();
        $booking = Booking::findOrFail($request->booking_id);

        if ($booking->rider_user_id !== $user->id) {
            return $this->error('Unauthorized', [], 403);
        }

        try {
            $confirmed = $this->paymentService->confirmStripePayment(
                $booking,
                $request->payment_intent_id
            );

            if (!$confirmed) {
                return $this->error('Payment confirmation failed');
            }

            // Confirm booking
            $this->bookingService->confirmBooking($booking);

            return $this->success([
                'booking' => $booking->fresh(),
                'payment_status' => 'paid',
            ], 'Payment confirmed and booking confirmed');
        } catch (\Exception $e) {
            return $this->error('Payment confirmation failed: ' . $e->getMessage());
        }
    }
}
