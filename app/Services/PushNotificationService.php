<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shop;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\FirebaseException;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = (new Factory)
            ->withServiceAccount(env('FIREBASE_CREDENTIALS'))
            ->createMessaging();
    }

    /**
     * Generic send to multiple tokens
     */
    public function sendToTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = []
    ): void {
        if (empty($tokens)) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(
                Notification::create($title, $body)
            )
            ->withData($data);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);

            // 🔥 Remove invalid tokens automatically
            foreach ($report->invalidTokens() as $invalidToken) {
                DeviceToken::where('token', $invalidToken)->delete();
            }
        } catch (MessagingException|FirebaseException $e) {
            Log::error('FCM send failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Payment success notification
     */
    public function sendPaymentSuccess(Payment $payment): void
    {
        $tokens = DeviceToken::where('user_id', $payment->userid)
            ->pluck('token')
            ->toArray();

        $this->sendToTokens(
            $tokens,
            'Payment Successful',
            'Your ABA payment was successful',
            [
                'type'     => 'payment',
                'order_id' => (string) $payment->orderid,
                'tran_id'  => (string) data_get($payment->raw_response, 'aba_tran_id'),
                'status'   => 'paid',
            ]
        );
    }

    /**
     * Order status update notification
     */
    public function sendOrderStatusUpdate($order): void
    {
        $tokens = DeviceToken::where('user_id', $order->userid)
            ->pluck('token')
            ->toArray();
    
        $this->sendToTokens(
            $tokens,
            'Order Status Updated',
            "Your order #{$order->id} is now {$order->status}",
            [
                'type'     => 'order_status',
                'order_id' => (string) $order->id,
                'status'   => $order->status,
            ]
        );
    }

    public function sendOrderCreated(Order $order): void
 {
    $tokens = DeviceToken::where('user_id', $order->userid)
        ->pluck('token')
        ->toArray();

    if (empty($tokens)) {
        return;
    }

    $this->sendToTokens(
        $tokens,
        'Order Placed',
        "Your order #{$order->id} has been placed successfully.",
        [
            'type' => 'order_created',
            'order_id' => (string)$order->id,
            'status' => $order->status,
        ]
    );
    }
 
    /**
     * New order notification to store owner
     */
    
    public function sendNewOrderToStoreOwner(Order $order): void
    {
        // Load shop + owner
        $order->load('shop.owner');
    
        if (!$order->shop || !$order->shop->owner) {
            return;
        }
    
        $ownerId = $order->shop->owner->id;
    
        // Get store owner's device tokens
        $tokens = DeviceToken::where('user_id', $ownerId)
            ->pluck('token')
            ->toArray();
    
        if (empty($tokens)) {
            return;
        }
    
        $this->sendToTokens(
            $tokens,
            'New Order Received',
            "You have a new order #{$order->id}",
            [
                'type'     => 'new_order',
                'order_id' => (string)$order->id,
                'shop_id'  => (string)$order->shop->id,
            ]
        );
    }
    

}
