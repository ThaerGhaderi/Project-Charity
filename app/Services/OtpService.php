<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Http; // ✅ استدعاء مكتبة الـ HTTP للاتصال بـ Brevo

class OtpService
{
    public function generateOtp(): string
    {
        return str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function sendOtp(string $email, string $type = 'verification'): Otp
    {
        // حذف الـ OTP القديمة لنفس البريد الإلكتروني ونفس النوع
        Otp::where('email', $email)
            ->where('type', $type)
            ->delete();

        $otpCode = $this->generateOtp();

        $otp = Otp::create([
            'email' => $email,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
        ]);

        // ✅ التصحيح: إرسال لربط الـ API الصحيح لـ Brevo مع تثبيت إيميل المرسل الموثق لديك
        Http::withHeaders([
            'api-key' => env('BREVO_API_KEY'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://brevo.com', [
            'sender' => [
                'name' => env('MAIL_FROM_NAME', 'Laravel Charity'),
                'email' => 'mlkalglam@gmail.com' // تثبيت إيميل بريفو الموثق حتماً هنا لضمان الإرسال
            ],
            'to' => [
                [
                    'email' => $email,
                    'name' => 'User'
                ]
            ],
            'subject' => 'رمز التحقق الخاص بك (OTP) - Charity',
            'htmlContent' => '<h3>مرحباً بك في جمعية Charity</h3><p>رمز التحقق الخاص بك لتفعيل الحساب هو: <b style="font-size: 20px; color: #4F46E5;">' . $otpCode . '</b></p><p>هذا الرمز صالح لمدة 10 دقائق فقط.</p>'
        ]);

        return $otp;
    }

    public function verifyOtp(string $email, string $otpCode, string $type = 'verification'): bool
    {
        $otp = Otp::where('email', $email)
            ->where('otp', trim((string)$otpCode))
            ->where('type', $type)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otp || !$otp->isValid()) {
            return false;
        }

        $otp->update(['is_used' => true]);

        return true;
    }
}
