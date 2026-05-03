<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Mail;

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
            'email' => $email,           // ✅ أضف هذا
            'otp' => $otpCode,
            'type' => $type,             // ✅ أضف هذا
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
        ]);

        Mail::raw("Your OTP code is: {$otpCode}", function ($message) use ($email) {
            $message->to($email)->subject('OTP Code');
        });

        return $otp;
    }

    public function verifyOtp(string $email, string $otpCode, string $type = 'verification'): bool
    {
        $otp = Otp::where('email', $email)
            ->where('otp', $otpCode)
            ->where('type', $type)
            ->where('is_used', false)
            ->first();

        if (!$otp || !$otp->isValid()) {
            return false;
        }

        $otp->update(['is_used' => true]);

        return true;
    }
}