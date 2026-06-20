<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayerurlService
{
    protected $publicKey;
    protected $secretKey;
    protected $baseUrl;
    protected $isSandbox;

    public function __construct()
    {
        $this->publicKey = config('payerurl.public_key');
        $this->secretKey = config('payerurl.secret_key');
        $this->baseUrl = config('payerurl.api_url');
        $this->isSandbox = config('payerurl.sandbox');
    }

    /**
     * إنشاء طلب دفع جديد
     */
    public function createPayment(array $data): array
    {
        try {
            $payload = [
                'amount' => (float) $data['amount'],
                'currency' => $data['currency'] ?? config('payerurl.currency'),
                'invoice_id' => $data['invoice_id'],
                'description' => $data['description'] ?? 'تبرع خيري',
                'customer' => [
                    'name' => $data['customer_name'] ?? 'Guest',
                    'email' => $data['customer_email'] ?? '',
                ],
                'redirect_url' => $data['redirect_url'],
                'cancel_url' => $data['cancel_url'],
                'webhook_url' => route('payerurl.webhook'),
            ];

            $response = Http::withBasicAuth($this->publicKey, $this->secretKey)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/payments', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'payment_id' => $response->json('id'),
                    'redirect_url' => $response->json('redirect_url'),
                    'qr_code' => $response->json('qr_code'),
                    'expires_at' => $response->json('expires_at'),
                ];
            }

            Log::error('PayerURL payment creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'فشل في إنشاء طلب الدفع',
            ];

        } catch (\Exception $e) {
            Log::error('PayerURL exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ في الاتصال ببوابة الدفع',
            ];
        }
    }

    /**
     * التحقق من حالة الدفع
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = Http::withBasicAuth($this->publicKey, $this->secretKey)
                ->get($this->baseUrl . '/payments/' . $paymentId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('status'),
                    'amount' => $response->json('amount'),
                    'currency' => $response->json('currency'),
                    'paid_at' => $response->json('paid_at'),
                    'transaction_hash' => $response->json('transaction_hash'),
                ];
            }

            return [
                'success' => false,
                'message' => 'فشل في التحقق من حالة الدفع',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}