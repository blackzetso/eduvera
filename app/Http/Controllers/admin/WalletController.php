<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Services\PricingService;
use App\Services\FawaterakService;
use App\Models\WalletRechargeRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    protected WalletService $walletService;
    protected PricingService $pricingService;
    protected FawaterakService $fawaterakService;

    public function __construct(
        WalletService $walletService, 
        PricingService $pricingService,
        FawaterakService $fawaterakService
    ) {
        $this->walletService = $walletService;
        $this->pricingService = $pricingService;
        $this->fawaterakService = $fawaterakService;
    }

    /**
     * Display wallet dashboard
     */
    public function index()
    {
        $wallet = $this->walletService->getOrCreateWallet();

        // Get recent transactions
        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get pending recharge requests
        $pendingRecharges = $wallet->rechargeRequests()
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get pricing info
        $pricingInfo = $this->pricingService->getPricingInfo();

        // Get consumption charts from Bunny
        $consumptionCharts = $this->getConsumptionCharts();

        return Inertia::render('Admin/theme1/Wallet/Index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'pendingRecharges' => $pendingRecharges,
            'pricingInfo' => $pricingInfo,
            'needsActivation' => $this->walletService->needsActivation(),
            'consumptionCharts' => $consumptionCharts,
        ]);
    }

    /**
     * Show recharge page
     */
    public function showRecharge()
    {
        $wallet = $this->walletService->getOrCreateWallet();
        $pricingInfo = $this->pricingService->getPricingInfo();

        // Check if Fawaterak is configured
        $fawaterakStatus = $this->fawaterakService->getConfigurationStatus();
        if (!$fawaterakStatus['is_configured']) {
            return redirect()->route('admin.wallet.index')
                ->with('error', 'خدمة الدفع Fawaterak غير مفعلة. يرجى التواصل مع الإدارة.');
        }

        return Inertia::render('Admin/theme1/Wallet/Recharge', [
            'wallet' => $wallet,
            'pricingInfo' => $pricingInfo,
            'usdToEgp' => $pricingInfo['usd_to_egp_rate'],
            'fawaterakConfigured' => $fawaterakStatus['is_configured'],
        ]);
    }

    /**
     * Initiate recharge
     */
    public function recharge(Request $request)
    {
        $this->authorize('finance.walletAdjust');

        $request->validate([
            'amount' => 'required|numeric|min:0.25|max:10000', // TODO: Change back to min:10 after testing
            'payment_method_id' => 'nullable|integer|in:0,2,3,4,5,6,7,11,14,30',
        ]);

        $wallet = $this->walletService->getOrCreateWallet();

        // Use payment_method_id from request, default to 0 (show all methods)
        $paymentMethodId = $request->input('payment_method_id', 0);

        // Create recharge request
        $rechargeRequest = $wallet->rechargeRequests()->create([
            'amount' => $request->amount,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_gateway' => 'fawaterak',
            'payment_method_id' => $paymentMethodId,
        ]);

        Log::info('Recharge request created', [
            'request_id' => $rechargeRequest->id,
            'amount' => $request->amount,
            'payment_method_id' => $paymentMethodId,
        ]);

        // Redirect to payment (will be implemented with Fawaterak integration)
        return redirect()->route('admin.wallet.payment', $rechargeRequest->id);
    }

    /**
     * Process payment
     */
    public function processPayment(WalletRechargeRequest $rechargeRequest)
    {
        if ($rechargeRequest->status !== 'pending') {
            return redirect()->route('admin.wallet.index')
                ->with('error', 'هذا الطلب تمت معالجته بالفعل');
        }

        $amountEgp = $this->pricingService->usdToEgp($rechargeRequest->amount);
        $user = auth()->user();

        try {
            // Create Fawaterak payment
            $paymentData = $this->fawaterakService->createPayment(
                $amountEgp,
                'EGP',
                [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email ?? '',
                    'phone' => $user->phone ?? '',
                ],
                route('admin.wallet.payment.callback', ['request_id' => $rechargeRequest->id]),
                $rechargeRequest->payment_method_id
            );

            if (!$paymentData) {
                return redirect()->route('admin.wallet.index')
                    ->with('error', 'فشل في إنشاء رابط الدفع. يرجى المحاولة لاحقاً.');
            }

            // Store transaction ID
            $invoiceId = $paymentData['data']['invoice_id'] 
                ?? $paymentData['invoice_id'] 
                ?? $paymentData['reference_id'] 
                ?? null;
            
            $rechargeRequest->update([
                'transaction_id' => $invoiceId,
                'gateway_response' => $paymentData,
            ]);

            // Check payment method type and handle accordingly
            $paymentMethodData = $paymentData['data']['payment_data'] ?? null;
            
            // For redirect methods (Credit Card, Valu, etc.)
            $paymentUrl = $paymentMethodData['redirectTo'] ?? null;
            
            if ($paymentUrl) {
                Log::info('Redirecting to Fawaterak payment page', ['url' => $paymentUrl]);
                // Use Inertia::location to force a full page redirect (bypass CORS)
                return Inertia::location($paymentUrl);
            }
            
            // For non-redirect methods (Fawry, Mobile Wallets, Aman, Basata)
            // Show payment code/QR in our site
            if ($paymentMethodData) {
                Log::info('Showing payment code page', ['payment_method_id' => $rechargeRequest->payment_method_id]);
                return redirect()->route('admin.wallet.payment.show-code', $rechargeRequest->id);
            }

            // Fallback: if no payment data at all
            Log::warning('No payment data found in Fawaterak response', ['payment_data' => $paymentData]);
            return redirect()->route('admin.wallet.index')
                ->with('error', 'لم يتم الحصول على بيانات الدفع من Fawaterak. يرجى المحاولة لاحقاً.');
        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'recharge_request_id' => $rechargeRequest->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.wallet.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show payment code page (for Fawry, Mobile Wallets, etc.)
     */
    public function showPaymentCode(WalletRechargeRequest $rechargeRequest)
    {
        if ($rechargeRequest->status !== 'pending') {
            return redirect()->route('admin.wallet.index')
                ->with('info', 'هذا الطلب تمت معالجته بالفعل');
        }

        $gatewayResponse = $rechargeRequest->gateway_response;
        $paymentData = $gatewayResponse['data']['payment_data'] ?? null;
        
        if (!$paymentData) {
            return redirect()->route('admin.wallet.index')
                ->with('error', 'لم يتم العثور على بيانات الدفع');
        }

        // Determine payment method type based on response data
        $paymentInfo = [
            'type' => null,
            'data' => $paymentData,
            'invoice_id' => $gatewayResponse['data']['invoice_id'] ?? null,
        ];

        if (isset($paymentData['fawryCode'])) {
            $paymentInfo['type'] = 'fawry';
            $paymentInfo['code'] = $paymentData['fawryCode'];
            $paymentInfo['expire_date'] = $paymentData['expireDate'] ?? null;
        } elseif (isset($paymentData['meezaReference']) || isset($paymentData['meezaQrCode'])) {
            $paymentInfo['type'] = 'meeza';
            $paymentInfo['reference'] = $paymentData['meezaReference'] ?? null;
            $paymentInfo['qr_code'] = $paymentData['meezaQrCode'] ?? null;
        } elseif (isset($paymentData['amanCode'])) {
            $paymentInfo['type'] = 'aman';
            $paymentInfo['code'] = $paymentData['amanCode'];
        } elseif (isset($paymentData['masaryCode'])) {
            $paymentInfo['type'] = 'basata';
            $paymentInfo['code'] = $paymentData['masaryCode'];
        }

        return Inertia::render('Admin/theme1/Wallet/PaymentCode', [
            'rechargeRequest' => $rechargeRequest->load('wallet'),
            'paymentInfo' => $paymentInfo,
            'amountEgp' => $this->pricingService->usdToEgp($rechargeRequest->amount),
        ]);
    }

    /**
     * Cancel a pending recharge request
     */
    public function cancelRecharge(WalletRechargeRequest $rechargeRequest)
    {
        if ($rechargeRequest->status !== 'pending') {
            return back()->with('error', 'لا يمكن إلغاء هذا الطلب');
        }

        $rechargeRequest->update(['status' => 'cancelled']);
        
        Log::info('Recharge request cancelled', [
            'request_id' => $rechargeRequest->id,
            'amount' => $rechargeRequest->amount,
        ]);

        return back()->with('success', 'تم إلغاء طلب الشحن بنجاح');
    }

    /**
     * Check payment status manually
     */
    public function checkPaymentStatus(WalletRechargeRequest $rechargeRequest)
    {
        if ($rechargeRequest->status !== 'pending') {
            return back()->with('info', 'هذا الطلب تمت معالجته بالفعل');
        }

        if (!$rechargeRequest->transaction_id) {
            return back()->with('error', 'لم يتم العثور على معرف المعاملة');
        }

        try {
            $paymentData = $this->fawaterakService->verifyPayment($rechargeRequest->transaction_id);
            
            if ($paymentData) {
                $status = $paymentData['data']['status'] 
                    ?? $paymentData['data']['payment_status']
                    ?? $paymentData['status'] 
                    ?? null;
                
                $statusText = $paymentData['data']['status_text'] ?? null;
                
                // Check for successful payment
                $isPaid = false;
                if ($status === 1 || $status === '1') {
                    $isPaid = true;
                }
                if (is_string($status) && in_array(strtolower($status), ['paid', 'success', 'successful', 'completed'])) {
                    $isPaid = true;
                }
                if ($statusText && in_array(strtolower($statusText), ['paid', 'success', 'successful', 'completed'])) {
                    $isPaid = true;
                }
                
                if ($isPaid) {
                    $rechargeRequest->markAsCompleted($rechargeRequest->transaction_id, $paymentData);
                    $amount = rtrim(rtrim(number_format($rechargeRequest->amount, 6, '.', ''), '0'), '.');
                    return back()->with('success', 'تم تأكيد الدفع بنجاح! تم إضافة $' . $amount . ' لرصيدك');
                }
                
                // Still pending
                return back()->with('info', 'الدفع لا يزال قيد المعالجة. يرجى المحاولة لاحقاً.');
            }
            
            return back()->with('warning', 'لم يتم العثور على بيانات الدفع. يرجى المحاولة لاحقاً.');
        } catch (\Exception $e) {
            Log::error('Error checking payment status', [
                'recharge_request_id' => $rechargeRequest->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'حدث خطأ أثناء التحقق من حالة الدفع');
        }
    }

    /**
     * Payment callback
     */
    public function paymentCallback(Request $request)
    {
        Log::info('Payment callback received', [
            'all_params' => $request->all(),
            'query_params' => $request->query(),
        ]);

        // Fawaterak may send invoice_id or request_id
        $requestId = $request->query('request_id');
        $invoiceId = $request->query('invoice_id');
        $paymentStatus = $request->query('payment_status') ?? $request->query('status');

        // If invoice_id is provided, find recharge request by transaction_id
        if ($invoiceId && !$requestId) {
            Log::info('Searching by invoice_id', ['invoice_id' => $invoiceId]);
            $rechargeRequest = WalletRechargeRequest::where('transaction_id', $invoiceId)->first();
            
            if ($rechargeRequest) {
                $requestId = $rechargeRequest->id;
                Log::info('Found recharge request by invoice_id', ['request_id' => $requestId]);
            }
        }

        if (!$requestId) {
            Log::warning('Payment callback: No request_id or invoice_id provided');
            return redirect()->route('admin.wallet.index')
                ->with('error', 'معلومات الدفع غير صحيحة');
        }

        $rechargeRequest = $rechargeRequest ?? WalletRechargeRequest::find($requestId);

        if (!$rechargeRequest) {
            Log::error('Payment callback: Recharge request not found', [
                'request_id' => $requestId,
                'invoice_id' => $invoiceId,
            ]);
            return redirect()->route('admin.wallet.index')
                ->with('error', 'طلب الشحن غير موجود');
        }

        // Check if already completed
        if ($rechargeRequest->isCompleted()) {
            Log::info('Payment callback: Request already completed', ['request_id' => $requestId]);
            $balance = rtrim(rtrim(number_format($rechargeRequest->wallet->balance, 4, '.', ''), '0'), '.');
            return redirect()->route('admin.wallet.index')
                ->with('success', 'تم شحن المحفظة بنجاح مسبقاً! رصيدك الحالي: $' . $balance);
        }

        // Verify payment with Fawaterak
        if ($rechargeRequest->transaction_id) {
            $paymentData = $this->fawaterakService->verifyPayment($rechargeRequest->transaction_id);
            
            if ($paymentData) {
                // Fawaterak may return status in different locations and formats
                $status = $paymentData['data']['status'] 
                    ?? $paymentData['data']['payment_status']
                    ?? $paymentData['status'] 
                    ?? $paymentData['payment_status'] 
                    ?? null;
                
                $statusText = $paymentData['data']['status_text'] ?? null;
                
                Log::info('Payment verification result', [
                    'request_id' => $requestId,
                    'transaction_id' => $rechargeRequest->transaction_id,
                    'status' => $status,
                    'status_text' => $statusText,
                ]);

                // Check for successful payment
                // Fawaterak may return: status=1 (paid), status="paid", or status_text="paid"
                $isPaid = false;
                
                // Check if status is integer 1 (paid)
                if ($status === 1 || $status === '1') {
                    $isPaid = true;
                }
                
                // Check if status or status_text is string "paid"
                if (is_string($status) && in_array(strtolower($status), ['paid', 'success', 'successful', 'completed'])) {
                    $isPaid = true;
                }
                
                if ($statusText && in_array(strtolower($statusText), ['paid', 'success', 'successful', 'completed'])) {
                    $isPaid = true;
                }
                
                if ($isPaid) {
                    $rechargeRequest->markAsCompleted(
                        $rechargeRequest->transaction_id,
                        $paymentData
                    );

                    Log::info('Payment completed successfully', [
                        'request_id' => $requestId,
                        'amount' => $rechargeRequest->amount,
                        'new_balance' => $rechargeRequest->wallet->balance,
                    ]);

                    $amount = rtrim(rtrim(number_format($rechargeRequest->amount, 6, '.', ''), '0'), '.');
                    $balance = rtrim(rtrim(number_format($rechargeRequest->wallet->balance, 4, '.', ''), '0'), '.');
                    return redirect()->route('admin.wallet.index')
                        ->with('success', 'تم شحن المحفظة بنجاح! تم إضافة $' . $amount . ' - رصيدك الحالي: $' . $balance);
                }

                // Check for pending payment
                // Status 0 = pending in Fawaterak
                if ($status === 0 || $status === '0' || (is_string($status) && in_array(strtolower($status), ['pending', 'processing']))) {
                    Log::info('Payment is pending', ['request_id' => $requestId]);
                    return redirect()->route('admin.wallet.index')
                        ->with('warning', 'عملية الدفع قيد المعالجة. سيتم تحديث رصيدك فور تأكيد الدفع.');
                }
            } else {
                Log::warning('Payment verification returned no data', [
                    'request_id' => $requestId,
                    'transaction_id' => $rechargeRequest->transaction_id,
                ]);
            }
        }

        // Payment failed or cancelled
        $rechargeRequest->markAsFailed([
            'callback_status' => $paymentStatus,
            'callback_params' => $request->all(),
        ]);

        Log::warning('Payment failed or cancelled', [
            'request_id' => $requestId,
            'status' => $paymentStatus,
        ]);

        return redirect()->route('admin.wallet.index')
            ->with('error', 'فشلت عملية الدفع أو تم إلغاؤها. يرجى المحاولة مرة أخرى.');
    }

    /**
     * Payment webhook
     */
    public function paymentWebhook(Request $request)
    {
        Log::info('Fawaterak webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        $payload = $request->all();
        
        // Extract transaction ID from various possible locations
        $transactionId = $payload['data']['invoice_id'] 
            ?? $payload['invoice_id'] 
            ?? $payload['reference_id'] 
            ?? null;

        if (!$transactionId) {
            Log::error('Webhook: No transaction ID in payload');
            return response()->json(['status' => 'error', 'message' => 'No transaction ID'], 400);
        }

        // Find recharge request
        $rechargeRequest = WalletRechargeRequest::where('transaction_id', $transactionId)->first();

        if (!$rechargeRequest) {
            Log::warning('Webhook: Recharge request not found', ['transaction_id' => $transactionId]);
            return response()->json(['status' => 'not_found'], 404);
        }

        // Check if already processed
        if ($rechargeRequest->isCompleted()) {
            Log::info('Webhook: Request already completed', ['request_id' => $rechargeRequest->id]);
            return response()->json(['status' => 'already_processed']);
        }

        // Extract payment status
        $status = $payload['data']['status'] 
            ?? $payload['status'] 
            ?? $payload['payment_status'] 
            ?? null;
        
        $statusText = $payload['data']['status_text'] ?? null;

        Log::info('Webhook: Processing payment', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'status_text' => $statusText,
            'request_id' => $rechargeRequest->id,
        ]);

        // Handle successful payment
        // Status 1 = paid, or string "paid"
        $isPaid = ($status === 1 || $status === '1');
        if (is_string($status)) {
            $isPaid = $isPaid || in_array(strtolower($status), ['paid', 'success', 'successful', 'completed']);
        }
        if ($statusText) {
            $isPaid = $isPaid || in_array(strtolower($statusText), ['paid', 'success', 'successful', 'completed']);
        }
        
        if ($isPaid) {
            $rechargeRequest->markAsCompleted($transactionId, $payload);
            Log::info('Webhook: Payment completed successfully', [
                'request_id' => $rechargeRequest->id,
                'amount' => $rechargeRequest->amount,
                'new_balance' => $rechargeRequest->wallet->balance,
            ]);
            return response()->json(['status' => 'success', 'message' => 'Payment processed']);
        }

        // Handle failed payment
        if (in_array(strtolower($status), ['failed', 'cancelled', 'canceled'])) {
            $rechargeRequest->markAsFailed($payload);
            Log::info('Webhook: Payment failed', ['request_id' => $rechargeRequest->id]);
            return response()->json(['status' => 'failed', 'message' => 'Payment failed']);
        }

        // Pending or unknown status
        Log::info('Webhook: Payment status pending or unknown', [
            'request_id' => $rechargeRequest->id,
            'status' => $status,
        ]);

        return response()->json(['status' => 'received']);
    }

    /**
     * Sync consumption from Bunny
     */
    public function syncConsumption()
    {
        $this->authorize('finance.financialCorrection');

        $result = $this->walletService->syncConsumptionFromBunny();

        if ($result['success']) {
            return back()->with('success', 'تم مزامنة الاستهلاك بنجاح');
        }

        return back()->with('error', $result['message'] ?? 'فشلت عملية المزامنة');
    }

    /**
     * Get consumption charts data from Bunny
     */
    protected function getConsumptionCharts(): array
    {
        try {
            $libraryId = config('services.bunny.stream_library_id');
            $apiKey = config('services.bunny.stream_api_key');

            if (!$libraryId || !$apiKey) {
                return ['available' => false];
            }

            $bunnyService = app(\App\Services\BunnyLibraryService::class);
            
            // جلب بيانات الـ Library الفعلية
            $libraryDetails = $bunnyService->getLibraryDetails($libraryId);
            $stats = $bunnyService->getLibraryStatistics($libraryId, $apiKey);

            if (!$libraryDetails) {
                return ['available' => false];
            }

            // البيانات الصحيحة من libraryDetails
            $totalStorage = ($libraryDetails['StorageUsage'] ?? 0) / (1024 ** 3);
            $totalBandwidth = ($libraryDetails['TrafficUsage'] ?? 0) / (1024 ** 3);

            // Log للـ debugging
            Log::info('Bunny consumption charts data', [
                'total_storage' => $totalStorage,
                'total_bandwidth' => $totalBandwidth,
                'storage_bytes' => $libraryDetails['StorageUsage'] ?? 0,
                'bandwidth_bytes' => $libraryDetails['TrafficUsage'] ?? 0,
            ]);

            // Parse charts data
            $viewsChart = $stats['viewsChart'] ?? [];

            // تحويل للمصفوفات للـ charts
            $dates = [];
            $storageData = [];
            $bandwidthData = [];
            
            // حساب تقديري للـ daily data
            $dailyStorage = $totalStorage / max(count($viewsChart), 1);
            
            foreach ($viewsChart as $date => $views) {
                $dates[] = date('Y-m-d', strtotime($date));
                // Storage موزع على الأيام
                $storageData[] = round($dailyStorage, 2);
                // Bandwidth تقديري من المشاهدات
                $bandwidthData[] = round($views * 0.1, 2);
            }

        // عكس الترتيب لعرض أحدث تاريخ أولاً
        $last30Dates = array_slice($dates, -30);
        $last30Storage = array_slice($storageData, -30);
        $last30Bandwidth = array_slice($bandwidthData, -30);
        
        return [
            'available' => true,
            'totalStorage' => round($totalStorage, 2),
            'totalBandwidth' => round($totalBandwidth, 2),
            'dates' => array_reverse($last30Dates), // آخر 30 يوم - الأحدث أولاً
            'storageData' => array_reverse($last30Storage),
            'bandwidthData' => array_reverse($last30Bandwidth),
        ];
        } catch (\Exception $e) {
            Log::error('Error fetching consumption charts: ' . $e->getMessage());
            return ['available' => false];
        }
    }

    /**
     * Test Fawaterak configuration
     */
    public function testFawaterak()
    {
        $configStatus = $this->fawaterakService->getConfigurationStatus();
        $connectionTest = $this->fawaterakService->testConnection();

        return response()->json([
            'configuration' => $configStatus,
            'connection_test' => $connectionTest,
        ]);
    }
}
