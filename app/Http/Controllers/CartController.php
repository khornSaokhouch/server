<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $carts = Cart::with('items.item')->get();
        return response()->json($carts);
    }

    public function show(Cart $cart)
    {
        $cart->load('items.item');
        return response()->json($cart);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'shop_id' => 'required|exists:shops,id',
            'promo_id' => 'nullable|integer',
            'status' => 'nullable|in:active,converted,abandoned',
        ]);

        $cart = Cart::create($validated);
        return response()->json(['message' => 'Cart created', 'data' => $cart], 201);
    }

    public function update(Request $request, Cart $cart)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,converted,abandoned',
            'subtotal_cents' => 'nullable|integer',
            'discount_cents' => 'nullable|integer',
            'total_cents' => 'nullable|integer',
        ]);

        $cart->update($validated);
        return response()->json(['message' => 'Cart updated', 'data' => $cart]);
    }

    public function destroy(Cart $cart)
    {
        $cart->delete();
        return response()->json(['message' => 'Cart deleted']);
    }
}
