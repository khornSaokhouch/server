<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cart_id' => 'required|exists:carts,id',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'unit_price_cents' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $cartItem = CartItem::create($validated);
        return response()->json(['message' => 'Cart item added', 'data' => $cartItem], 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'unit_price_cents' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $cartItem->update($validated);
        return response()->json(['message' => 'Cart item updated', 'data' => $cartItem]);
    }

    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();
        return response()->json(['message' => 'Cart item deleted']);
    }
}
