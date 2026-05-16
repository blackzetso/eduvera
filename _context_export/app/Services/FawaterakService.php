<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FawaterakService
{
    protected string $apiKey;
    protected string $merchantId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.fawaterak.api_key');
        $this->merchantId = config('services.fawaterak.merchant_id');
        $this->baseUrl = config('services.fawaterak.base_url', 'https://staging.fawaterk.com/api/v2');
    }

    /**
     * Create payment invoice
     */
    public function createPayment(float $amount, string $currency, array $customerData, string $callbackUrl, ?int $paymentMethodId = null): ?array
    {
        // Validate configuration
        if (empty($this->apiKey)) {
            Log::error('Fawaterak API Key is not configured');
            throw new \Exception('إعدادات Fawaterak غير مكتملة. يرجى التحقق من FAWATERAK_API_KEY');
        }

        if (empty($this->merchantId)) {
            Log::error('Fawaterak Merchant ID is not configured');
            throw new \Exception('إعدادات Fawaterak غير مكتملة. يرجى التحقق من FAWATERAK_MERCHANT_ID');
        }

        try {
            Log::info('Creating Fawaterak payment', [
                'amount' => $amount,
                'currency' => $currency,
                'callback_url' => $callbackUrl,
                'payment_method_id' => $paymentMethodId,
            ]);

            // Build request payload according to Fawaterak API documentation
            $payload = [
                'cartTotal' => $amount,
                'currency' => $currency,
                'customer' => [
                    'first_name' => $customerData['first_name'] ?? 'Customer',
                    'last_name' => $customerData['last_name'] ?? 'User',
                    'email' => $customerData['email'] ?? '',
                    'phone' => $customerData['phone'] ?? '',
                ],
                'redirectionUrls' => [
                    'successUrl' => $callbackUrl,
                    'failUrl' => $callbackUrl,
                    'pendingUrl' => $callbackUrl,
                ],
                'cartItems' => [
                    [
                        'name' => 'Storage Wallet Recharge',
                        'price' => $amount,
                        'quantity' => 1,
                    ]
                ],
            ];

            // Add payment method if specified
            // Note: 0 means "all available methods" - let Fawaterak show selection page
            if ($paymentMethodId !== null && $paymentMethodId !== 0) {
                $payload['payment_method_id'] = $paymentMethodId;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/invoiceInitPay", $payload);

            // Log raw response for debugging
            Log::info('Fawaterak API Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'raw_body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if response is valid JSON
                if ($data === null) {
                    Log::error('Fawaterak returned empty or invalid JSON on success', [
                        'status' => $response->status(),
                        'raw_body' => $response->body(),
                    ]);
                    throw new \Exception('حدث خطأ: رد غير صالح من خدمة الدفع Fawaterak.');
                }
                
                Log::info('Fawaterak payment created successfully', [
                    'full_response' => $data,
                    'invoice_id' => $data['invoice_id'] ?? null,
                    'reference_id' => $data['reference_id'] ?? null,
                    'url' => $data['url'] ?? null,
                    'payment_url' => $data['payment_url'] ?? null,
                ]);
                return $data;
            }

            // Parse error response
            $errorBody = $response->json();
            
            // Log error details
            Log::error('Fawaterak API error response', [
                'status' => $response->status(),
                'error_body' => $errorBody,
                'raw_body' => $response->body(),
            ]);
            
            $errorMessage = $this->parseErrorMessage($errorBody);

            Log::error('Fawaterak payment creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'parsed_error' => $errorMessage,
            ]);

            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            Log::error('Fawaterak API exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse error message from Fawaterak response
     */
    protected function parseErrorMessage(?array $errorBody): string
    {
        // Handle null or empty response
        if ($errorBody === null || empty($errorBody)) {
            return 'فشل الاتصال بخدمة الدفع Fawaterak. لم يتم استقبال رد صحيح من السيرفر.';
        }

        if (isset($errorBody['message'])) {
            if (is_array($errorBody['message'])) {
                // Handle validation errors
                $errors = [];
                foreach ($errorBody['message'] as $field => $messages) {
                    if (is_array($messages)) {
                        $errors[] = implode(', ', $messages);
                    } else {
                        $errors[] = $messages;
                    }
                }
                $errorText = implode('. ', $errors);

                // Check for specific errors
                if (stripos($errorText, 'Invalid Token') !== false || stripos($errorText, 'inactive vendor') !== false) {
                    return 'خطأ في إعدادات Fawaterak: API Token غير صحيح أو الحساب غير مفعل. يرجى التحقق من الإعدادات أو التواصل مع دعم Fawaterak.';
                }
                
                if (stripos($errorText, 'not allow to use this payment method') !== false) {
                    return 'هذه الطريقة غير متاحة حالياً. يرجى اختيار طريقة دفع أخرى أو التواصل مع الإدارة.';
                }

                return 'خطأ من Fawaterak: ' . $errorText;
            }
            return 'خطأ من Fawaterak: ' . $errorBody['message'];
        }

        return 'فشل الاتصال بخدمة الدفع Fawaterak. يرجى المحاولة لاحقاً.';
    }

    /**
     * Verify payment
     */
    public function verifyPayment(string $transactionId): ?array
    {
        try {
            Log::info('Verifying Fawaterak payment', ['transaction_id' => $transactionId]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/getInvoiceData/{$transactionId}");

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Fawaterak payment verification successful', [
                    'transaction_id' => $transactionId,
                    'full_response' => $data,
                    'status' => $data['data']['status'] ?? $data['status'] ?? null,
                    'payment_status' => $data['data']['payment_status'] ?? $data['payment_status'] ?? null,
                ]);
                return $data;
            }

            Log::error('Fawaterak payment verification failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Fawaterak verification exception: ' . $e->getMessage(), [
                'transaction_id' => $transactionId,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Handle webhook from Fawaterak
     */
    public function handleWebhook(array $payload): bool
    {
        try {
            // Validate webhook signature if needed
            // Process payment status
            $status = $payload['payment_status'] ?? null;
            $transactionId = $payload['reference_id'] ?? $payload['invoice_id'] ?? null;

            if ($status === 'paid' || $status === 'success') {
                Log::info('Fawaterak webhook: Payment successful', [
                    'transaction_id' => $transactionId,
                ]);
                return true;
            }

            Log::warning('Fawaterak webhook: Payment not successful', [
                'status' => $status,
                'transaction_id' => $transactionId,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Fawaterak webhook processing error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): ?string
    {
        $paymentData = $this->verifyPayment($transactionId);

        if (!$paymentData) {
            return null;
        }

        return $paymentData['payment_status'] ?? $paymentData['status'] ?? null;
    }

    /**
     * Check if Fawaterak is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->merchantId);
    }

    /**
     * Get configuration status with details
     */
    public function getConfigurationStatus(): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'has_api_key' => !empty($this->apiKey),
            'has_merchant_id' => !empty($this->merchantId),
            'base_url' => $this->baseUrl,
            'environment' => str_contains($this->baseUrl, 'staging') ? 'staging' : 'production',
        ];
    }

    /**
     * Test connection to Fawaterak API
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'إعدادات Fawaterak غير مكتملة',
                'details' => $this->getConfigurationStatus(),
            ];
        }

        try {
            // Try to create a minimal test request (will fail but shows if credentials work)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/");

            // Even if endpoint doesn't exist, 401/403 means auth issue, 404 means auth is OK
            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'success' => false,
                    'message' => 'API Token غير صحيح أو الحساب غير مفعل',
                    'status_code' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'message' => 'الاتصال بـ Fawaterak API ناجح',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'فشل الاتصال بـ Fawaterak: ' . $e->getMessage(),
            ];
        }
    }
}

