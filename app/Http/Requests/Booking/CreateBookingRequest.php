<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth checked via middleware
    }

    public function rules(): array
    {
        $cashEnabled = SystemSetting::get('payments.method_cash_enabled', true);
        $razorpayEnabled = SystemSetting::get('payments.razorpay_enabled', false);
        $stripeEnabled = SystemSetting::get('payments.stripe_enabled', false);

        $paymentMethods = [];
        if ($cashEnabled) $paymentMethods[] = 'cash';
        if ($razorpayEnabled) $paymentMethods[] = 'razorpay';
        if ($stripeEnabled) $paymentMethods[] = 'stripe';

        return [
            'ride_id' => 'required|exists:rides,id',
            'seats' => 'required|integer|min:1',
            'payment_method' => 'required|in:' . implode(',', $paymentMethods ?: ['cash']),
        ];
    }
}
