<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send OTP SMS.
     */
    public function sendOtp(string $phone, string $code): bool
    {
        $provider = SystemSetting::get('auth.otp_provider', 'firebase');

        if ($provider === 'firebase') {
            // Firebase phone auth is handled client-side
            return true;
        }

        if ($provider === 'custom_sms') {
            return $this->sendCustomSms($phone, $code);
        }

        return false;
    }

    /**
     * Send SMS via custom provider.
     */
    protected function sendCustomSms(string $phone, string $code): bool
    {
        $customProvider = SystemSetting::get('auth.custom_sms_provider', 'msg91');
        $config = SystemSetting::get('auth.custom_sms_config', []);

        try {
            return match ($customProvider) {
                'msg91' => $this->sendViaMsg91($phone, $code, $config),
                'twilio' => $this->sendViaTwilio($phone, $code, $config),
                'generic_http' => $this->sendViaGenericHttp($phone, $code, $config),
                default => false,
            };
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'provider' => $customProvider,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send via MSG91.
     */
    protected function sendViaMsg91(string $phone, string $code, array $config): bool
    {
        $authKey = $config['api_key'] ?? SystemSetting::get('msg91_auth_key');
        $senderId = $config['sender_id'] ?? 'BLABLA';
        $templateId = $config['template_id'] ?? null;

        $url = 'https://api.msg91.com/api/v5/otp';
        
        $data = [
            'authkey' => $authKey,
            'mobile' => $phone,
            'message' => "Your OTP is {$code}. Valid for 5 minutes.",
        ];

        if ($templateId) {
            $data['template_id'] = $templateId;
        }

        $response = Http::post($url, $data);

        return $response->successful();
    }

    /**
     * Send via Twilio.
     */
    protected function sendViaTwilio(string $phone, string $code, array $config): bool
    {
        $accountSid = $config['account_sid'] ?? '';
        $authToken = $config['auth_token'] ?? '';
        $from = $config['from_number'] ?? '';

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post($url, [
                'From' => $from,
                'To' => $phone,
                'Body' => "Your OTP is {$code}. Valid for 5 minutes.",
            ]);

        return $response->successful();
    }

    /**
     * Send via Generic HTTP.
     */
    protected function sendViaGenericHttp(string $phone, string $code, array $config): bool
    {
        $baseUrl = $config['base_url'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $bodyTemplate = $config['body_template'] ?? '{"phone": "{phone}", "message": "{message}"}';
        $messageTemplate = $config['message_template'] ?? 'Your OTP is {code}. Valid for 5 minutes.';

        $message = str_replace('{code}', $code, $messageTemplate);
        $body = str_replace(['{phone}', '{message}'], [$phone, $message], $bodyTemplate);
        
        $bodyData = json_decode($body, true) ?? ['phone' => $phone, 'message' => $message];

        $response = Http::withHeaders($headers);

        if ($method === 'GET') {
            $response = $response->get($baseUrl, $bodyData);
        } else {
            $response = $response->post($baseUrl, $bodyData);
        }

        return $response->successful();
    }

    /**
     * Test SMS sending.
     */
    public function test(string $phone): bool
    {
        $testCode = '123456';
        return $this->sendOtp($phone, $testCode);
    }
}

