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

    public function sendOtp(string $identifier, string $type = 'verification'): Otp
    {
        Otp::where('identifier', $identifier)
            ->where('type', $type)
            ->delete();

        $otpCode = $this->generateOtp();

        $otp = Otp::create([
            'identifier' => $identifier,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
            'is_used' => false,
        ]);

        Mail::raw("Your OTP code is: {$otpCode}", function ($message) use ($identifier) {
            $message->to($identifier)->subject('OTP Code');
        });

        return $otp;
    }

    public function verifyOtp(string $identifier, string $otpCode, string $type = 'verification'): bool
    {
        $otp = Otp::where('identifier', $identifier)
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