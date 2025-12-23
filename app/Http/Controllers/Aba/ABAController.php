<?php

namespace App\Http\Controllers\Aba;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ABAController extends Controller
{
    /**
     * =================================================
     * Generate ABA QR (KHQR / ABA App)
     * =================================================
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

        $orderId = $request->input('order_id');
        $userId  = $request->input('user_id');

        $paymentOption = 'abapay_khqr';
        $purchaseType  = 'purchase';
        $lifetime      = 6;
        $qrTemplate    = 'template3_color';

        $firstName = $request->input('first_name', '');
        $lastName  = $request->input('last_name', '');
        $email     = $request->input('email', '');
        $phone     = $request->input('phone', '');

        $items = $request->input('items');
        if (is_array($items)) {
            $items = base64_encode(json_encode($items));
        }

        // ✅ CALLBACK URL MUST BE PLAIN (NOT BASE64)
        $callbackUrl = base64_encode(config('aba.callback_url'));

        // ✅ RETURN DEEPLINK MUST BE BASE64 JSON
        $returnDeeplink = base64_encode(json_encode([
            'android_scheme' => 'yourapp://aba-success',
            'ios_scheme'     => 'yourapp://aba-success',
        ]));

        // ================= HASH (ORDER MATTERS) =================
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

        // ================= CREATE PAYMENT =================
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

        // ================= CALL ABA =================
        $response = Http::post($apiUrl, [
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
            'callback_url'      => $callbackUrl, // ✅ PLAIN URL
            'return_deeplink'   => $returnDeeplink,
            'lifetime'          => $lifetime,
            'qr_image_template' => $qrTemplate,
            'hash'              => $hash,
        ]);

        if (! $response->successful()) {
            Log::error('ABA QR FAILED', $response->body());
            abort(500, 'ABA QR generation failed');
        }

        $data = $response->json();

        // ================= SAVE ABA RESPONSE =================
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
            'status'    => 'pending',
        ]);
    }

    /**
     * =================================================
     * ABA CALLBACK (Webhook)
     * =================================================
     */
    public function callback(Request $request)
    {
        Log::info('ABA CALLBACK RECEIVED', $request->all());

        $tranId = $request->input('tran_id');

        if (! $tranId) {
            return response()->json(['status' => 'ignored']);
        }

        $payment = Payment::where('raw_response->aba_tran_id', $tranId)->first();

        if (! $payment) {
            Log::warning('ABA callback: payment not found', ['tran_id' => $tranId]);
            return response()->json(['status' => 'ignored']);
        }

        // Save callback payload
        $payment->update([
            'status' => 'pending',
            'raw_response' => array_merge(
                $payment->raw_response ?? [],
                ['callback' => $request->all()]
            ),
        ]);

        // Verify with ABA
        $this->checkTransaction($payment);

        return response()->json(['status' => 'ok']);
    }

    /**
     * =================================================
     * Check Transaction API
     * =================================================
     */
    private function checkTransaction(Payment $payment): void
    {
        $merchantId = config('aba.merchant_id');
        $hashKey    = config('aba.hash_key');
        $apiUrl     = config('aba.check_url');

        $tranId  = data_get($payment->raw_response, 'aba_tran_id');
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
            Log::error('ABA CHECK FAILED', $response->body());
            return;
        }

        $data = $response->json();

        // ✅ DEV AUTO-SUCCESS (REMOVE IN PROD)
        $isPaid = app()->environment('local')
            ? true
            : (($data['status'] ?? null) === '00');

        $payment->update([
            'status' => $isPaid ? 'paid' : 'failed',
            'payment_method_type'  => $isPaid ? 'aba_qr' : null,
            'payment_method_brand' => $isPaid ? 'ABA' : null,
            'payment_method_last4' => null,
            'raw_response' => array_merge(
                $payment->raw_response ?? [],
                ['check_response' => $data]
            ),
        ]);

        if ($isPaid && $payment->orderid) {
            Order::where('id', $payment->orderid)->update([
                'status' => 'pending',
            ]);
        }
        if ($isPaid) {
            app(PushNotificationService::class)
                ->sendPaymentSuccess($payment);
        }
    } 

    /**
     * =================================================
     * Flutter Polling Endpoint
     * =================================================
     */
    public function status(Request $request)
    {
        $tranId = $request->query('tran_id');

        if (! $tranId) {
            return response()->json(['status' => 'invalid'], 400);
        }

        $payment = Payment::where('raw_response->aba_tran_id', $tranId)->first();

        if (! $payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $payment->status, // initiated | pending | paid | failed
        ]);
    }
}
