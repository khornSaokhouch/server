<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Webhook;

class PaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe PaymentIntent for a custom card flow (Stripe Elements).
     */
    public function createPaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'userid' => 'nullable|exists:users,id',
            'orderid' => 'nullable|exists:orders,id',
        ]);

        $currency = $validated['currency'] ?? 'usd';

        $intent = PaymentIntent::create([
            'amount' => $validated['amount_cents'],
            'currency' => $currency,
            'metadata' => [
                'userid' => $validated['userid'] ?? '',
                'orderid' => $validated['orderid'] ?? '',
            ],
        ]);

        // store a payment record (optional)
        $payment = Payment::create([
            'userid' => $validated['userid'] ?? null,
            'orderid' => $validated['orderid'] ?? null,
            'stripe_payment_intent_id' => $intent->id,
            'status' => $intent->status,
            'amount_cents' => $validated['amount_cents'],
            'currency' => $currency,
            'raw_response' => $intent->toArray(),
        ]);

        return response()->json([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'payment' => $payment,
        ]);
    }

    /**
     * Create a Checkout Session (hosted Stripe checkout).
     */
    public function createCheckoutSession(Request $request)
    {
        $validated = $request->validate([
            'line_items' => 'required|array|min:1',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
            'userid' => 'nullable|exists:users,id',
            'orderid' => 'nullable|exists:orders,id',
        ]);

        // Example line_items element: {price_data: {currency, product_data: {name}, unit_amount}, quantity}
        $session = CheckoutSession::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => $validated['line_items'],
            'success_url' => $validated['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $validated['cancel_url'],
            'metadata' => [
                'userid' => $validated['userid'] ?? '',
                'orderid' => $validated['orderid'] ?? '',
            ],
        ]);

        // record session
        $payment = Payment::create([
            'userid' => $validated['userid'] ?? null,
            'orderid' => $validated['orderid'] ?? null,
            'stripe_session_id' => $session->id,
            'status' => $session->payment_status ?? 'open',
            'amount_cents' => null,
            'currency' => null,
            'raw_response' => $session->toArray(),
        ]);

        return response()->json([
            'sessionId' => $session->id,
            'url' => $session->url ?? null, // url for hosted checkout (Stripe may return)
            'payment' => $payment,
        ]);
    }

    /**
     * Webhook endpoint that Stripe will call.
     * Configure the endpoint URL in Stripe dashboard and set STRIPE_WEBHOOK_SECRET.
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $intent = $event->data->object;
                // update payment record
                Payment::where('stripe_payment_intent_id', $intent->id)
                    ->update([
                        'status' => $intent->status,
                        'amount_cents' => $intent->amount,
                        'currency' => $intent->currency,
                        'raw_response' => $intent->toArray(),
                    ]);
                // TODO: mark order as paid, fulfill
                break;

            case 'checkout.session.completed':
                $session = $event->data->object;
                Payment::where('stripe_session_id', $session->id)
                    ->update([
                        'status' => $session->payment_status ?? 'paid',
                        'raw_response' => $session->toArray(),
                    ]);
                // TODO: mark order as paid/fulfilled
                break;

            case 'payment_intent.payment_failed':
                $intent = $event->data->object;
                Payment::where('stripe_payment_intent_id', $intent->id)
                    ->update([
                        'status' => $intent->status,
                        'raw_response' => $intent->toArray(),
                    ]);
                break;

            default:
                // Unexpected event type
                Log::info('Received unhandled Stripe event: ' . $event->type);
        }

        return response()->json(['received' => true]);
    }


    
    
    public function getPaymentsByUser($userId)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    
        $payments = Payment::where('userid', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                if ($payment->stripe_payment_intent_id) {
                    $intent = PaymentIntent::retrieve([
                        'id' => $payment->stripe_payment_intent_id,
                        'expand' => ['latest_charge.payment_method_details'],
                    ]);
    
                    $payment->payment_method_type =
                        $intent->latest_charge?->payment_method_details?->type;
                }
    
                return $payment;
            });
    
        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }


public function getPaymentsByOrder($orderId)
{
    Stripe::setApiKey(config('services.stripe.secret'));

    $payments = Payment::where('orderid', $orderId)
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($payment) {

            $payment->payment_method_type = null;
            $payment->payment_method_brand = null;
            $payment->payment_method_last4 = null;

            if ($payment->stripe_payment_intent_id) {
                $intent = PaymentIntent::retrieve([
                    'id' => $payment->stripe_payment_intent_id,
                    'expand' => ['latest_charge.payment_method_details'],
                ]);

                // ONLY exists when payment succeeded
                if ($intent->latest_charge) {
                    $details = $intent->latest_charge->payment_method_details;

                    $payment->payment_method_type = $details->type ?? null;

                    if ($details->type === 'card') {
                        $payment->payment_method_brand =
                            $details->card->brand ?? null;

                        $payment->payment_method_last4 =
                            $details->card->last4 ?? null;
                    }
                }
            }

            return $payment;
        });

    return response()->json([
        'success' => true,
        'data' => $payments,
    ]);
}

public function status($orderId)
{
    $payment = Payment::where('order_id', $orderId)->latest()->first();

    return response()->json([
        'paid' => $payment?->status === 'PAID',
    ]);
}


    
}
