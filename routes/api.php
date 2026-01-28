<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::get('/settings', [SettingsController::class, 'index']);
Route::get('/health', [\App\Http\Controllers\Api\HealthController::class, 'check']);

// Cities and locations (public)
Route::prefix('cities')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CityController::class, 'index']);
    Route::get('/{slug}', [\App\Http\Controllers\Api\CityController::class, 'show']);
    Route::get('/{city_id}/service-areas', [\App\Http\Controllers\Api\CityController::class, 'serviceAreas']);
    Route::post('/resolve', [\App\Http\Controllers\Api\CityController::class, 'resolve']);
});

Route::post('/location/check', [\App\Http\Controllers\Api\LocationController::class, 'check']);

// Webhooks (public, but should be secured in production)
Route::prefix('webhooks')->group(function () {
    Route::post('/razorpay', [\App\Http\Controllers\Api\WebhookController::class, 'razorpay']);
    Route::post('/stripe', [\App\Http\Controllers\Api\WebhookController::class, 'stripe']);
});

// Rides (public)
Route::prefix('rides')->group(function () {
    Route::get('/search', [\App\Http\Controllers\Api\RideController::class, 'search']);
    Route::get('/{id}', [\App\Http\Controllers\Api\RideController::class, 'show']);
});

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/social', [AuthController::class, 'socialLogin']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
});

// Protected routes (require authentication)
Route::middleware(['auth:sanctum', 'device.lastseen'])->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // Devices
    Route::post('/devices/register', [DeviceController::class, 'register']);
    Route::post('/devices/unregister', [DeviceController::class, 'unregister']);
    
    // Settings
    Route::get('/settings/all', [SettingsController::class, 'all']);
    
    // Driver routes
    Route::prefix('driver')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\DriverController::class, 'me']);
        Route::post('/apply', [\App\Http\Controllers\Api\DriverController::class, 'apply']);
        Route::post('/selfie', [\App\Http\Controllers\Api\DriverController::class, 'uploadSelfie']);
        Route::post('/documents/{key}', [\App\Http\Controllers\Api\DriverController::class, 'uploadDocument']);
        Route::post('/submit', [\App\Http\Controllers\Api\DriverController::class, 'submit']);
    });
    
    // Vehicle routes
    Route::prefix('driver/vehicles')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\VehicleController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\VehicleController::class, 'store']);
        Route::put('/{id}', [\App\Http\Controllers\Api\VehicleController::class, 'update']);
        Route::post('/{id}/photos', [\App\Http\Controllers\Api\VehicleController::class, 'uploadPhotos']);
        Route::delete('/{id}/photos/{media_id}', [\App\Http\Controllers\Api\VehicleController::class, 'deletePhoto']);
        Route::post('/{id}/set-primary', [\App\Http\Controllers\Api\VehicleController::class, 'setPrimary']);
    });
    
    // Driver ride routes
    Route::prefix('driver/rides')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Driver\RideController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Driver\RideController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Driver\RideController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\Driver\RideController::class, 'update']);
        Route::post('/{id}/publish', [\App\Http\Controllers\Api\Driver\RideController::class, 'publish']);
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\Driver\RideController::class, 'cancel']);
    });
    
    // Ride views (optional analytics)
    Route::post('/rides/{id}/view', [\App\Http\Controllers\Api\RideController::class, 'recordView']);
    
    // Chat routes
    Route::prefix('conversations')->group(function () {
        Route::get('/{booking_id}', [\App\Http\Controllers\Api\ChatController::class, 'getConversation']);
        Route::post('/{booking_id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
        Route::post('/{booking_id}/read', [\App\Http\Controllers\Api\ChatController::class, 'markAsRead']);
    });
    
    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/my', [\App\Http\Controllers\Api\NotificationController::class, 'myNotifications']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    });
    
    // Booking routes
    Route::prefix('bookings')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\BookingController::class, 'store']);
        Route::get('/my', [\App\Http\Controllers\Api\BookingController::class, 'myBookings']);
        Route::get('/{id}', [\App\Http\Controllers\Api\BookingController::class, 'show']);
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\BookingController::class, 'cancel']);
    });
    
    // Driver booking routes
    Route::prefix('driver/bookings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Driver\BookingController::class, 'index']);
        Route::post('/{id}/accept', [\App\Http\Controllers\Api\Driver\BookingController::class, 'accept']);
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\Driver\BookingController::class, 'reject']);
        Route::post('/{id}/cancel', [\App\Http\Controllers\Api\Driver\BookingController::class, 'cancel']);
    });
    
    // Driver wallet routes
    Route::prefix('driver/wallet')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Driver\WalletController::class, 'index']);
        Route::get('/transactions', [\App\Http\Controllers\Api\Driver\WalletController::class, 'transactions']);
    });
    
    // Driver payout routes
    Route::prefix('driver/payouts')->group(function () {
        Route::post('/request', [\App\Http\Controllers\Api\Driver\PayoutController::class, 'request']);
    });
    
    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/razorpay/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createRazorpayOrder']);
        Route::post('/razorpay/verify', [\App\Http\Controllers\Api\PaymentController::class, 'verifyRazorpay']);
        Route::post('/stripe/create-intent', [\App\Http\Controllers\Api\PaymentController::class, 'createStripeIntent']);
        Route::post('/stripe/confirm', [\App\Http\Controllers\Api\PaymentController::class, 'confirmStripe']);
    });
    
    // Rating routes
    Route::prefix('ratings')->group(function () {
        Route::get('/pending', [\App\Http\Controllers\Api\RatingController::class, 'pending']);
        Route::post('/', [\App\Http\Controllers\Api\RatingController::class, 'store']);
        Route::get('/users/{id}', [\App\Http\Controllers\Api\RatingController::class, 'userRatings']);
        Route::get('/users/{id}/trust', [\App\Http\Controllers\Api\RatingController::class, 'trustProfile']);
    });
    
    // Report routes
    Route::prefix('reports')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\ReportController::class, 'store']);
    });
    
    // Support routes
    Route::prefix('support')->group(function () {
        Route::get('/tickets/my', [\App\Http\Controllers\Api\SupportController::class, 'myTickets']);
        Route::post('/tickets', [\App\Http\Controllers\Api\SupportController::class, 'create']);
        Route::get('/tickets/{id}', [\App\Http\Controllers\Api\SupportController::class, 'show']);
        Route::post('/tickets/{id}/reply', [\App\Http\Controllers\Api\SupportController::class, 'reply']);
    });
    
    // CMS routes
    Route::prefix('cms')->group(function () {
        Route::get('/pages', [\App\Http\Controllers\Api\CmsController::class, 'pages']);
        Route::get('/pages/{slug}', [\App\Http\Controllers\Api\CmsController::class, 'page']);
        Route::get('/footer-pages', [\App\Http\Controllers\Api\CmsController::class, 'footerPages']);
    });
    
    // FAQ routes
    Route::get('/faqs', [\App\Http\Controllers\Api\CmsController::class, 'faqs']);
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/test-push', [\App\Http\Controllers\Api\Admin\TestPushController::class, 'send']);
        });
        
        Route::middleware('role:super_admin,city_admin')->group(function () {
            Route::get('/drivers', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'index']);
            Route::get('/drivers/{id}', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'show']);
            Route::post('/drivers/{id}/approve', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'approve']);
            Route::post('/drivers/{id}/reject', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'reject']);
            Route::post('/drivers/{id}/suspend', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'suspend']);
            Route::post('/driver-documents/{id}/approve', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'approveDocument']);
            Route::post('/driver-documents/{id}/reject', [\App\Http\Controllers\Api\Admin\DriverModerationController::class, 'rejectDocument']);
            
            // Booking moderation
            Route::get('/bookings', [\App\Http\Controllers\Api\Admin\BookingModerationController::class, 'index']);
            Route::get('/bookings/{id}', [\App\Http\Controllers\Api\Admin\BookingModerationController::class, 'show']);
            Route::post('/bookings/{id}/force-cancel', [\App\Http\Controllers\Api\Admin\BookingModerationController::class, 'forceCancel']);
            Route::post('/bookings/{id}/force-refund', [\App\Http\Controllers\Api\Admin\BookingModerationController::class, 'forceRefund']);
            
            // Payments
            Route::get('/payments', [\App\Http\Controllers\Api\Admin\PaymentController::class, 'index']);
            
            // Conversations (read-only)
            Route::get('/conversations', [\App\Http\Controllers\Api\Admin\ConversationController::class, 'index']);
            Route::get('/conversations/{id}', [\App\Http\Controllers\Api\Admin\ConversationController::class, 'show']);
            
            // Payouts
            Route::get('/payouts', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'index']);
            Route::post('/payouts/{id}/approve', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'approve']);
            Route::post('/payouts/{id}/reject', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'reject']);
            Route::post('/payouts/{id}/mark-paid', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'markPaid']);
            
            // Support
            Route::get('/support/tickets', [\App\Http\Controllers\Api\Admin\SupportController::class, 'index']);
            Route::get('/support/tickets/{id}', [\App\Http\Controllers\Api\Admin\SupportController::class, 'show']);
            Route::post('/support/tickets/{id}/reply', [\App\Http\Controllers\Api\Admin\SupportController::class, 'reply']);
            Route::post('/support/tickets/{id}/status', [\App\Http\Controllers\Api\Admin\SupportController::class, 'updateStatus']);
        });
    });
});

