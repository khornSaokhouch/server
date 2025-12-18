<?php


namespace App\Http\Controllers\Aba;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


//class ABAController extends Controller
//{
    /**
     * Generate ABA QR (KHQR / WeChat / Alipay)
  */
    // public function requestAOFQr(Request $request)
    // {
    //     // ================= CONFIG =================
    //     $merchantId = config('aba.merchant_id');
    //     $apiKey     = config('aba.public_key');
    //     $apiUrl     = 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/generate-qr';
    
    //     // ================= REQUIRED =================
    //     $reqTime  = now()->utc()->format('YmdHis');
    //     $tranId   = $request->input('tran_id', now()->format('YmdHis') . rand(100, 999));
    //     $amount   = $request->input('amount', 11);
    //     $currency = strtoupper($request->input('currency', 'USD'));
    //     $paymentOption = $request->input('payment_option', 'abapay_khqr');
    //     $lifetime = $request->input('lifetime', 6);
    //     $qrTemplate = $request->input('qr_image_template', 'template3_color');
    
    //     // ================= OPTIONAL =================
    //     $firstName = $request->input('first_name', '');
    //     $lastName  = $request->input('last_name', '');
    //     $email     = $request->input('email', '');
    //     $phone     = $request->input('phone', '');
    //     $purchaseType = $request->input('purchase_type', 'purchase');
    
    //     // Items (array → base64 JSON)
    //     $items = $request->input('items');
    //     if (is_array($items)) {
    //         $items = base64_encode(json_encode($items));
    //     }
    
    //     // Callback URL (Base64)
    //     $callbackUrl = base64_encode(config('aba.callback_url'));
    
    //     // Deeplink (Base64 JSON)
    //     $returnDeeplink = base64_encode(json_encode([
    //         'android_scheme' => 'yourapp://aba-success',
    //         'ios_scheme'     => 'yourapp://aba-success',
    //     ]));
    
    //     // ================= HASH (ORDER IS CRITICAL) =================
    //     $b4hash =
    //         $reqTime .
    //         $merchantId .
    //         $tranId .
    //         $amount .
    //         ($items ?? '') .
    //         $firstName .
    //         $lastName .
    //         $email .
    //         $phone .
    //         $purchaseType .
    //         $paymentOption .
    //         $callbackUrl .
    //         $returnDeeplink .
    //         $currency .
    //         '' . // custom_fields
    //         '' . // return_params
    //         '' . // payout
    //         $lifetime .
    //         $qrTemplate;
    
    //     $hash = base64_encode(
    //         hash_hmac('sha512', $b4hash, $apiKey, true)
    //     );
    
    //     // ================= PAYLOAD =================
    //     $payload = [
    //         'req_time'          => $reqTime,
    //         'merchant_id'       => $merchantId,
    //         'tran_id'           => $tranId,
    //         'first_name'        => $firstName ?: null,
    //         'last_name'         => $lastName ?: null,
    //         'email'             => $email ?: null,
    //         'phone'             => $phone ?: null,
    //         'amount'            => $amount,
    //         'currency'          => $currency,
    //         'purchase_type'     => $purchaseType,
    //         'payment_option'    => $paymentOption,
    //         'items'             => $items,
    //         'callback_url'      => $callbackUrl,
    //         'return_deeplink'   => $returnDeeplink,
    //         'lifetime'          => $lifetime,
    //         'qr_image_template' => $qrTemplate,
    //         'hash'              => $hash,
    //     ];
    
    //     // ================= CALL ABA =================
    //     $response = Http::withHeaders([
    //         'Content-Type' => 'application/json',
    //     ])->post($apiUrl, $payload);
    
    //     if (! $response->successful()) {
    //         Log::error('ABA QR API FAILED', [
    //             'status' => $response->status(),
    //             'body'   => $response->body(),
    //         ]);
    
    //         return response()->json([
    //             'message' => 'Failed to generate ABA QR',
    //         ], 500);
    //     }
    
    //     $data = $response->json();
    
    //     // ================= NORMALIZED RESPONSE =================
    //     return response()->json([
    //         'tran_id'    => $tranId,
    //         'qr_string'  => $data['qrString'] ?? null,
    //         'qr_image'   => $data['qrImage'] ?? null,
    //         'deeplink'   => $data['abapay_deeplink'] ?? null,
    //         'app_store'  => $data['app_store'] ?? null,
    //         'play_store' => $data['play_store'] ?? null,
    //         'amount'     => $data['amount'] ?? $amount,
    //         'currency'   => $data['currency'] ?? $currency,
    //         'status'     => $data['status'] ?? null,
    //     ]);
    // }
    

    // /**
    //  * ABA Pushback Callback
    //  */
    // public function callback(Request $request)
    // {
    //     Log::info('ABA QR CALLBACK RECEIVED', $request->all());

    //     // Example payload:
    //     // {
    //     //   "tran_id": "123456789",
    //     //   "apv": 123456,
    //     //   "status": "00",
    //     //   "merchant_ref_no": "REF_xxx"
    //     // }

    //     // ⚠️ Best practice:
    //     // 1. Save callback data
    //     // 2. Mark transaction as PENDING
    //     // 3. Call Check Transaction API
    //     // 4. Mark as PAID only after verification

    //     return response()->json(['status' => 'ok']);
    // }

    
//}



class ABAController extends Controller
{
    /**
     * Generate ABA QR
     */
    public function requestAOFQr(Request $request)
    {
        $merchantId = config('aba.merchant_id');
        $hashKey    = config('aba.hash_key');
        $apiUrl     = config('aba.qr_url');

        $reqTime  = now()->utc()->format('YmdHis');
        $tranId   = now()->format('YmdHis') . rand(100, 999);
        $amount   = number_format((float) $request->input('amount'), 2, '.', '');
        $currency = strtoupper($request->input('currency', 'USD'));

        $orderId = $request->input('order_id'); // YOUR order ID (nullable)
        $userId = $request->input('user_id');   // YOUR user ID (nullable)

        $paymentOption = 'abapay_khqr';
        $lifetime      = 6;
        $qrTemplate    = 'template3_color';
        $purchaseType = 'purchase';

        $firstName = $request->input('first_name', '');
        $lastName  = $request->input('last_name', '');
        $email     = $request->input('email', '');
        $phone     = $request->input('phone', '');

        $items = $request->input('items');
        if (is_array($items)) {
            $items = base64_encode(json_encode($items));
        }

        $callbackUrl = base64_encode(config('aba.callback_url'));

        $returnDeeplink = base64_encode(json_encode([
            'android_scheme' => 'yourapp://aba-success',
            'ios_scheme'     => 'yourapp://aba-success',
        ]));

        // HASH (ORDER MATTERS)
        $b4hash =
            $reqTime .
            $merchantId .
            $tranId .
            $amount .
            ($items ?? '') .
            $firstName .
            $lastName .
            $email .
            $phone .
            $purchaseType .
            $paymentOption .
            $callbackUrl .
            $returnDeeplink .
            $currency .
            $lifetime .
            $qrTemplate;

        $hash = base64_encode(
            hash_hmac('sha512', $b4hash, $hashKey, true)
        );

        // CREATE PAYMENT RECORD
        $payment = Payment::create([
            'userid'       => $userId,
            'orderid'      => $orderId,
            'status'       => 'initiated',
            'amount_cents' => (int) bcmul($amount, 100),
            'currency'     => $currency,
            'raw_response' => [
                'aba_tran_id' => $tranId,
            ],
        ]);

        // ABA REQUEST
        $payload = [
            'req_time'          => $reqTime,
            'merchant_id'       => $merchantId,
            'tran_id'           => $tranId,
            'first_name'        => $firstName ?: null,
            'last_name'         => $lastName ?: null,
            'email'             => $email ?: null,
            'phone'             => $phone ?: null,
            'amount'            => $amount,
            'currency'          => $currency,
            'purchase_type'     => $purchaseType,
            'payment_option'    => $paymentOption,
            'items'             => $items,
            'callback_url'      => $callbackUrl,
            'return_deeplink'   => $returnDeeplink,
            'lifetime'          => $lifetime,
            'qr_image_template' => $qrTemplate,
            'hash'              => $hash,
        ];

        $response = Http::post($apiUrl, $payload);

        if (! $response->successful()) {
            Log::error('ABA QR FAILED', $response->json());
            abort(500, 'ABA QR generation failed');
        }

        $data = $response->json();

        // MERGE ABA RESPONSE
        $payment->update([
            'raw_response' => array_merge(
                $payment->raw_response ?? [],
                ['qr_response' => $data]
            ),
        ]);

        return response()->json([
            'tran_id'   => $tranId,
            'qr_string' => $data['qrString'] ?? null,
            'qr_image'  => $data['qrImage'] ?? null,
            'deeplink'  => $data['abapay_deeplink'] ?? null,
            'status'    => 'initiated',
        ]);
    }

    /**
     * ABA Callback
     */
    public function callback(Request $request)
    {
        Log::info('ABA CALLBACK', $request->all());

        $tranId = $request->input('tran_id');

        $payment = Payment::where('raw_response->aba_tran_id', $tranId)->first();

        if (! $payment) {
            Log::warning('ABA callback: payment not found', $request->all());
            return response()->json(['status' => 'ignored']);
        }

        $payment->update([
            'status' => 'pending',
            'raw_response' => array_merge(
                $payment->raw_response ?? [],
                ['callback' => $request->all()]
            ),
        ]);

        $this->checkTransaction($tranId);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Check Transaction API
     */
    private function checkTransaction(string $tranId): void
    {
        $merchantId = config('aba.merchant_id');
        $hashKey    = config('aba.hash_key');
        $apiUrl     = config('aba.check_url');

        $reqTime = now()->utc()->format('YmdHis');

        $b4hash = $reqTime . $merchantId . $tranId;

        $hash = base64_encode(
            hash_hmac('sha512', $b4hash, $hashKey, true)
        );

        $response = Http::post($apiUrl, [
            'req_time'    => $reqTime,
            'merchant_id' => $merchantId,
            'tran_id'     => $tranId,
            'hash'        => $hash,
        ]);

        if (! $response->successful()) {
            Log::error('ABA CHECK FAILED', $response->json());
            return;
        }

        $data = $response->json();

        $payment = Payment::where('raw_response->aba_tran_id', $tranId)->first();
        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => ($data['status'] ?? null) === '00' ? 'paid' : 'failed',
            'raw_response' => array_merge(
                $payment->raw_response ?? [],
                ['check_response' => $data]
            ),
        ]);
    }
}

