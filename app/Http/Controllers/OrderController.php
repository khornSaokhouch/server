<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * List orders (optionally filter by user or shop).
     */
    public function index(Request $request)
    {
        $query = Order::with(['orderItems.item.optionGroups.options','shop']);

        if ($request->filled('userid')) {
            $query->where('userid', $request->userid);
        }

        if ($request->filled('shopid')) {
            $query->where('shopid', $request->shopid);
        }

        // Sorting (whitelist)
        if ($request->filled('sort')) {
            $allowedSorts = ['id', 'userid', 'shopid', 'subtotalcents', 'totalcents', 'placedat'];
            $allowedDirections = ['asc', 'desc'];

            $sort = $request->get('sort');
            $direction = $request->get('direction', 'asc');

            if (in_array($sort, $allowedSorts) && in_array(strtolower($direction), $allowedDirections)) {
                $query->orderBy($sort, $direction);
            }
        }

        return $query->get();
    }

    /**
     * Show a single order.
     */
    public function show($id)
    {
        return Order::with(['orderItems.item','shop'])->findOrFail($id);
    }

    /**
     * Store a new order.
     *
     * Expected JSON structure:
     * {
     *   "userid": 1,
     *   "shopid": 1,
     *   "placedat": "2025-12-01 10:00:00", // optional, will use now() if omitted
     *   // optional: either promoid OR promocode
     *   "promoid": 1,
     *   "promocode": "FIXED100",
     *   "items": [
     *     {
     *       "itemid": 2,
     *       "unitprice_cents": 500,
     *       "quantity": 2,
     *       "namesnapshot": "Latte",
     *       "notes": "No sugar",
     *       "option_groups": [
     *         { "group_id": 1, "group_name": "Size", "selected_option": "M", "option_id": 1 },
     *         ...
     *       ]
     *     },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'userid' => ['required', 'exists:users,id'],
            'shopid' => ['required', 'exists:shops,id'],
            'placedat' => ['nullable', 'date'],
            'promoid' => ['nullable', 'exists:promotions,id'],
            'promocode' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.itemid' => ['required', 'exists:items,id'],
            'items.*.unitprice_cents' => ['required', 'integer', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.namesnapshot' => ['nullable', 'string', 'max:150'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
    
            // option_groups on each order item (nullable array)
            'items.*.option_groups' => ['nullable', 'array'],
            'items.*.option_groups.*.group_id' => ['required_with:items.*.option_groups', 'integer'],
            'items.*.option_groups.*.group_name' => ['required_with:items.*.option_groups', 'string', 'max:150'],
            'items.*.option_groups.*.selected_option' => ['required_with:items.*.option_groups', 'string', 'max:150'],
            'items.*.option_groups.*.option_id' => ['required_with:items.*.option_groups', 'integer'],
        ]);
    
        $placedAt = isset($validated['placedat']) ? Carbon::parse($validated['placedat']) : Carbon::now();
    
        // --- Calculate subtotal (server authoritative, integer cents) ---
        $subtotal = 0;
        foreach ($validated['items'] as $it) {
            $unit = (int) $it['unitprice_cents'];
            $qty = (int) $it['quantity'];
            $subtotal += $unit * $qty;
        }
    
        // --- Resolve promotion (by id or code) ---
        $promotion = null;
        if (!empty($validated['promoid'])) {
            $promotion = Promotion::find($validated['promoid']);
        } elseif (!empty($validated['promocode'])) {
            $promotion = Promotion::where('code', $validated['promocode'])->first();
        }
    
        $discount = 0;
    
        if ($promotion) {
            // Validate promotion is active and within date range
            $now = $placedAt;
    
            if (!$promotion->isactive) {
                return response()->json(['message' => 'Promotion is not active.'], 422);
            }
    
            if ($now->lt(Carbon::parse($promotion->startsat)) || $now->gt(Carbon::parse($promotion->endsat))) {
                return response()->json(['message' => 'Promotion is not valid at this time.'], 422);
            }
    
            // Optional: minimum order amount (in cents) to be eligible
            if (!empty($promotion->min_order_cents) && $subtotal < (int)$promotion->min_order_cents) {
                return response()->json(['message' => 'Order does not meet the promotion minimum order amount.'], 422);
            }
    
            // Enforce usage limit per user (interpretation: user can use X times)
            if (!is_null($promotion->usagelimit)) {
                $userUses = $promotion->orders()->where('userid', $validated['userid'])->count();
                if ($userUses >= $promotion->usagelimit) {
                    return response()->json(['message' => 'Promotion usage limit reached for this user.'], 422);
                }
            }
    
            // --- Calculate discount using integer cents math ---
            // Support both percent and fixedamount promotions.
            // If percent: promotion->value can be integer (10) or float/decimal (12.5)
            if ($promotion->type === 'percent') {
                // Normalize value to numeric (could be string/float)
                $percent = (float) $promotion->value;
                // discount = round(subtotal * percent / 100)
                $discount = (int) round($subtotal * ($percent / 100.0));
            } else { // fixedamount (assume stored in cents)
                $fixedamount = (float) $promotion->value;

                $discount = (int) $promotion->value; // expect cents
            }
    
            // Optional: enforce per-order max discount cap (in cents)
            if (!empty($promotion->max_discount_cents)) {
                $maxCap = (int) $promotion->max_discount_cents;
                if ($discount > $maxCap) {
                    $discount = $maxCap;
                }
            }
    
            // Ensure discount doesn't exceed subtotal
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
        }
    
        $total = $subtotal - $discount;
        if ($total < 0) $total = 0;
    
        // Save order & order items in a transaction
        DB::beginTransaction();
    
        try {
            $order = Order::create([
                'userid' => $validated['userid'],
                'shopid' => $validated['shopid'],
                'promoid' => $promotion ? $promotion->id : null,
                'status' => 'pending',
                'subtotalcents' => $subtotal,
                'discountcents' => $discount,
                'totalcents' => $total,
                'placedat' => $placedAt,
                'updatedat' => $placedAt,
            ]);
    
            // create items (including option_groups JSON)
            foreach ($validated['items'] as $it) {
                $oi = new OrderItem([
                    'itemid' => $it['itemid'],
                    'namesnapshot' => $it['namesnapshot'] ?? '',
                    'unitprice_cents' => (int) $it['unitprice_cents'],
                    'quantity' => (int) $it['quantity'],
                    'notes' => $it['notes'] ?? null,
                    'option_groups' => $it['option_groups'] ?? [], // cast to JSON if model has $casts
                ]);
                $order->orderItems()->save($oi);
            }
    
            DB::commit();
    
            $order->load('orderItems.item.optionGroups.options');
    
            return response()->json([
                'message' => 'Order created successfully.',
                'data' => $order
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * Update order status or other editable fields.
     */
    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending','paid','preparing','ready','completed','cancelled'])],
            // optionally allow updating updatedat
            'updatedat' => ['nullable', 'date'],
        ]);

        if (isset($validated['status'])) {
            $order->status = $validated['status'];
        }

        if (isset($validated['updatedat'])) {
            $order->updatedat = Carbon::parse($validated['updatedat']);
        } else {
            $order->updatedat = Carbon::now();
        }

        $order->save();

        return response()->json([
            'message' => 'Order updated.',
            'data' => $order
        ]);
    }

    /**
     * Delete an order.
     */
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted.']);
    }
}
