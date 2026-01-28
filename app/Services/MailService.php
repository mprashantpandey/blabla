<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;

class MailService
{
    /**
     * Configure mailer from system settings.
     */
    protected function configureMailer(): void
    {
        $host = SystemSetting::get('smtp.host');
        $port = SystemSetting::get('smtp.port', 587);
        $username = SystemSetting::get('smtp.username');
        $password = SystemSetting::get('smtp.password');
        $encryption = SystemSetting::get('smtp.encryption', 'tls');
        $fromEmail = SystemSetting::get('smtp.from_email');
        $fromName = SystemSetting::get('smtp.from_name', 'BlaBla');

        if (!$host || !$username || !$password) {
            return; // Use default mailer
        }

        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'timeout' => null,
            'auth_mode' => null,
        ]);

        Config::set('mail.from', [
            'address' => $fromEmail ?? config('mail.from.address'),
            'name' => $fromName,
        ]);
    }

    /**
     * Send email.
     */
    public function send(string $to, string $subject, string $view, array $data = []): bool
    {
        try {
            $this->configureMailer();

            Mail::send($view, $data, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send test email.
     */
    public function test(string $to): bool
    {
        return $this->send(
            $to,
            'Test Email from BlaBla',
            'emails.test',
            ['message' => 'This is a test email from BlaBla system.']
        );
    }
}

