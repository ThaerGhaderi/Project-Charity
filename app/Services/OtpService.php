<?php

namespace App\Services;

use App\Models\Otp;

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

        // ✅ إرسال كود الـ OTP عبر cURL الخام لتدمير حظر السيرفر السحابي والوصول لـ Brevo فوراً
        $curl = curl_init();

        $postData = [
            'sender' => [
                'name' => env('MAIL_FROM_NAME', 'Laravel Charity'),
                'email' => 'mlkalglam@gmail.com' // إيميل حساب بريفو الموثق لديك حتماً
            ],
            'to' => [
                [
                    'email' => $email,
                    'name' => 'User'
                ]
            ],
            'subject' => 'رمز التحقق الخاص بك (OTP) - Charity',
            'htmlContent' => '<h3>مرحباً بك في جمعية Charity</h3><p>رمز التحقق الخاص بك لتفعيل الحساب هو: <b style="font-size: 20px; color: #4F46E5;">' . $otpCode . '</b></p><p>هذا الرمز صالح لمدة 10 دقائق فقط.</p>'
        ];
curl_setopt_array($curl, [
            // ✅ تم تصحيح الرابط هنا
            CURLOPT_URL => 'https://api.brevo.com/v3/smtp/email',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . env('BREVO_API_KEY'), // تأكد من إضافة هذا المتغير في Railway
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        // ✅ إضافة تسجيل الأخطاء لمعرفة سبب المشكلة إذا فشل الإرسال مستقبلاً
        if ($err) {
            \Illuminate\Support\Facades\Log::error("cURL Error in Brevo: #:" . $err);
        } else {
            \Illuminate\Support\Facades\Log::info("Brevo Response: " . $response);
        }

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
