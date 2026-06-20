<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $secretKey;
    protected $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
        
        Stripe::setApiKey($this->secretKey);
    }

    /**
     * إنشاء Payment Intent
     */
    public function createPaymentIntent(array $data): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) ($data['amount'] * 100), // تحويل إلى سنتات
                'currency' => $data['currency'] ?? 'usd',
                'metadata' => [
                    'donation_id' => $data['donation_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                    'campaign_id' => $data['campaign_id'] ?? null,
                ],
                'description' => $data['description'] ?? 'تبرع خيري',
                'receipt_email' => $data['email'] ?? null,
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe payment creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * تأكيد الدفع (تحديث الحالة)
     */
    public function confirmPayment(string $paymentIntentId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'payment_intent_id' => $paymentIntent->id,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * التحقق من توقيع Webhook
     */
    public function verifyWebhookSignature($payload, $signature): bool
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
            
            return true;
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * معالجة Webhook Event
     */
    public function handleWebhookEvent(array $payload): array
    {
        $eventType = $payload['type'] ?? null;
        $data = $payload['data']['object'] ?? [];

        return [
            'type' => $eventType,
            'data' => $data,
            'payment_intent_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => ($data['amount'] ?? 0) / 100,
            'currency' => $data['currency'] ?? 'usd',
        ];
    }
}